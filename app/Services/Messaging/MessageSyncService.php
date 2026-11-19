<?php

namespace App\Services\Messaging;

use App\Models\Message;
use App\Models\MessageChannelState;
use App\Models\MessageThread;
use App\Models\SalesChannel;
use App\Models\User;
use App\Notifications\NewBuyerMessageNotification;
use App\Support\NotificationRecipients;
use App\Services\Messaging\Contracts\MessageChannelDriver;
use App\Services\Messaging\Contracts\ProvidesSystemThreads;
use App\Services\Messaging\Data\MessageData;
use App\Services\Messaging\Data\ThreadData;
use App\Services\Messaging\Exceptions\MessagingException;
use App\Services\Messaging\Exceptions\MissingScopeException;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Pulls a store's conversations through its channel driver and keeps message_threads /
 * messages up to date. Channel-agnostic: it only talks to the MessageChannelDriver contract.
 *
 * Safe to run repeatedly: threads and messages are matched on their platform ids, so a second
 * run over the same data changes nothing. The sync cursor only advances after a pass with no
 * failed thread, so a failure is retried on the next run instead of being skipped.
 */
class MessageSyncService
{
    /** How far back the very first sync of a store reaches. */
    public const DEFAULT_BACKFILL_DAYS = 30;

    private const LOCK_SECONDS = 600;
    /** Messages older than this are history (e.g. a backfill) and do not raise a bell notification. */
    private const NOTIFY_WITHIN_HOURS = 6;

    private const PREVIEW_LENGTH = 200;

    public function __construct(
        private MessageDriverManager $drivers,
        private ThreadLinker $linker,
        private AutoReplyService $autoReplies,
    ) {}

    /**
     * @param CarbonInterface|null $from           Re-read everything changed since this time (backfill / repair)
     * @param bool                 $includeSystem  Also sync platform system conversations (eBay payout/return notices)
     * @return array{threads_seen:int, threads_created:int, threads_updated:int, details_fetched:int, messages_created:int, linked_orders:int, errors:int, skipped:?string}
     *
     * @throws MessagingException when the thread list itself cannot be fetched (e.g. missing scope)
     */
    public function syncChannel(SalesChannel $channel, ?CarbonInterface $from = null, bool $includeSystem = false): array
    {
        $stats = [
            'threads_seen' => 0,
            'threads_created' => 0,
            'threads_updated' => 0,
            'details_fetched' => 0,
            'messages_created' => 0,
            'linked_orders' => 0,
            'errors' => 0,
            'skipped' => null,
        ];

        $state = MessageChannelState::forChannel($channel);

        if (!$state->messaging_enabled) {
            $stats['skipped'] = 'messaging is disabled for this store';
            return $stats;
        }

        // One sync per store at a time (scheduler, manual runs and queued jobs can overlap).
        $lock = Cache::lock("messages-sync:{$channel->id}", self::LOCK_SECONDS);
        if (!$lock->get()) {
            $stats['skipped'] = 'another sync of this store is already running';
            return $stats;
        }

        try {
            $driver = $this->drivers->forChannel($channel);

            if ($includeSystem && $driver instanceof ProvidesSystemThreads) {
                $driver->withSystemThreads();
            }

            $cursor = $from
                ? $driver->cursorFromTime($from)
                : ($state->cursor ?: $driver->cursorFromTime(now()->subDays(self::DEFAULT_BACKFILL_DAYS)));

            try {
                foreach ($driver->fetchThreads($channel, $cursor) as $summary) {
                    $stats['threads_seen']++;

                    try {
                        $this->syncThread($channel, $driver, $summary, $stats);
                    } catch (MissingScopeException $e) {
                        throw $e;
                    } catch (Throwable $e) {
                        $stats['errors']++;
                        Log::channel('messages')->error('Thread sync failed', [
                            'sales_channel_id' => $channel->id,
                            'external_thread_id' => $summary->externalThreadId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } catch (MessagingException $e) {
                $state->markFailed($e->getMessage());
                Log::channel('messages')->error('Message sync failed', [
                    'sales_channel_id' => $channel->id,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            $stats['linked_orders'] = $this->linker->relinkUnlinked($channel);

            if ($stats['errors'] === 0) {
                // A manual --from re-read must not move a stored cursor forward past data it did not cover.
                $advance = $from === null || !$state->cursor;
                $state->markSynced($advance ? $driver->nextCursor() : null);
            } else {
                $state->markFailed("{$stats['errors']} thread(s) failed to sync; the sync position was not advanced, so they are retried next run.");
            }
        } finally {
            $lock->release();
        }

        return $stats;
    }

    /**
     * @param array<string,int|string|null> $stats
     */
    private function syncThread(SalesChannel $channel, MessageChannelDriver $driver, ThreadData $summary, array &$stats): void
    {
        $thread = MessageThread::query()
            ->where('sales_channel_id', $channel->id)
            ->where('external_thread_id', $summary->externalThreadId)
            ->first();

        $isNew = $thread === null;
        $thread ??= new MessageThread([
            'sales_channel_id' => $channel->id,
            'external_thread_id' => $summary->externalThreadId,
        ]);

        $needsDetail = $isNew
            || !$thread->external_updated_at
            || ($summary->updatedAt && $summary->updatedAt->gt($thread->external_updated_at));

        $unreadChanged = !$isNew && $thread->unread_count !== $summary->unreadCount;

        if (!$needsDetail) {
            // Nothing new in the conversation; only the read state may have changed (e.g. read on the platform).
            if ($unreadChanged) {
                $thread->update(['unread_count' => $summary->unreadCount]);
                $stats['threads_updated']++;
            }
            return;
        }

        // Fields from the list call (it also carries the listing reference and unread count).
        $thread->fill([
            'thread_kind' => $this->keepOrUse($thread->thread_kind, $summary->threadKind),
            'buyer_username' => $summary->buyerUsername ?? $thread->buyer_username,
            'buyer_name' => $summary->buyerName ?? $thread->buyer_name,
            'buyer_external_id' => $summary->buyerExternalId ?? $thread->buyer_external_id,
            'subject' => $summary->subject ?? $thread->subject,
            'reference_type' => $summary->referenceType ?? $thread->reference_type,
            'external_reference_id' => $summary->externalReferenceId ?? $thread->external_reference_id,
            'unread_count' => $summary->unreadCount,
            'external_updated_at' => $summary->updatedAt ?? $thread->external_updated_at,
            'raw' => $summary->raw,
        ]);

        // The list call may carry only the latest message; fetch the whole conversation.
        $messages = $summary->messages;
        if (!$summary->hasFullHistory) {
            $messages = $driver->fetchThread($channel, $thread)->messages;
            $stats['details_fetched']++;
        }

        $stats['messages_created'] += $this->persistMessages($thread, $messages, $isNew);

        $this->linker->link($thread);

        $stats[$isNew ? 'threads_created' : 'threads_updated']++;
    }

    /**
     * Re-read one thread from the platform (used when staff open it in the inbox).
     * Read-only on the platform side; only our database changes.
     *
     * @return int New messages found
     *
     * @throws MessagingException
     */
    public function refreshThread(MessageThread $thread): int
    {
        $channel = $thread->salesChannel;
        $driver = $this->drivers->forChannel($channel);

        $detail = $driver->fetchThread($channel, $thread);

        // Remember how fresh this is, so the next list sync does not fetch the same conversation again.
        if ($detail->updatedAt && (!$thread->external_updated_at || $detail->updatedAt->gt($thread->external_updated_at))) {
            $thread->external_updated_at = $detail->updatedAt;
        }

        $created = $this->persistMessages($thread, $detail->messages, isNew: false);
        $this->linker->link($thread);

        return $created;
    }

    /**
     * Save the thread and store its messages, then recompute last-message fields and status.
     *
     * @param array<int,MessageData> $messages
     * @return int Messages newly created
     */
    private function persistMessages(MessageThread $thread, array $messages, bool $isNew): int
    {
        $newInboundAt = null;

        $created = DB::transaction(function () use ($thread, $messages, $isNew, &$newInboundAt) {
            $previousLastAt = $thread->last_message_at;
            $thread->save();

            $created = 0;
            $newInbound = false;
            foreach ($messages as $message) {
                if (!$this->storeMessage($thread, $message)) {
                    continue;
                }

                $created++;
                if ($message->direction === Message::DIRECTION_INBOUND
                    && (!$previousLastAt || ($message->sentAt && $message->sentAt->gt($previousLastAt)))) {
                    $newInbound = true;
                    $newInboundAt = $message->sentAt;
                }
            }

            $this->refreshDerivedFields($thread, $isNew, $newInbound);

            return $created;
        });

        if ($newInboundAt && $newInboundAt->gt(now()->subHours(self::NOTIFY_WITHIN_HOURS))) {
            $this->notifyTeam($thread);
        }

        if ($newInboundAt) {
            // Outside office hours the buyer gets one automatic answer. Never allowed to fail the sync.
            try {
                $this->autoReplies->maybeReply($thread);
            } catch (\Throwable $e) {
                Log::channel('messages')->error('Automatic reply check failed', ['thread_id' => $thread->id, 'error' => $e->getMessage()]);
            }
        }

        return $created;
    }

    /**
     * Bell notification for a fresh buyer message: to the assignee, or to the admins when unassigned.
     * Only for recent messages, so a backfill of old conversations does not flood the bell.
     * Never allowed to fail the sync.
     */
    private function notifyTeam(MessageThread $thread): void
    {
        try {
            $recipients = $thread->assigned_user_id
                ? User::query()->whereKey($thread->assigned_user_id)->get()
                : NotificationRecipients::admins();

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, NewBuyerMessageNotification::forThread($thread->loadMissing('salesChannel')));
            }
        } catch (\Throwable $e) {
            Log::channel('messages')->warning('Could not notify the team about a new buyer message', ['thread_id' => $thread->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Insert a message, or refresh the editable parts of an existing one.
     *
     * @return bool true when a new row was created
     */
    private function storeMessage(MessageThread $thread, MessageData $data): bool
    {
        $existing = Message::query()
            ->where('thread_id', $thread->id)
            ->where('external_message_id', $data->externalMessageId)
            ->first();

        // A reply sent from the inbox is stored before the platform reports it. If the send response carried
        // no message id (or the request timed out after the platform accepted it), adopt that row instead of
        // adding a duplicate bubble.
        if (!$existing && $data->direction === Message::DIRECTION_OUTBOUND) {
            $existing = Message::query()
                ->where('thread_id', $thread->id)
                ->whereNull('external_message_id')
                ->where('direction', Message::DIRECTION_OUTBOUND)
                ->where(fn ($q) => $q->whereNotNull('sent_by_user_id')->orWhere('is_auto_reply', true))
                ->where('body', trim($data->body))
                ->orderBy('id')
                ->first();

            if ($existing) {
                $existing->forceFill([
                    'external_message_id' => $data->externalMessageId,
                    'delivery_status' => Message::DELIVERY_SENT,
                    'error' => null,
                    'sent_at' => $data->sentAt ?? $existing->sent_at,
                ]);
            }
        }

        if ($existing) {
            // Keep our own metadata (who on the team sent it, auto-reply flag); only refresh platform data.
            $existing->fill([
                'body' => $data->body,
                'attachments' => $data->attachments ?: null,
                'raw' => $data->raw,
            ]);
            if ($data->isRead && !$existing->read_at) {
                $existing->read_at = now();
            }
            if ($existing->isDirty()) {
                $existing->save();
            }

            return false;
        }

        Message::create([
            'thread_id' => $thread->id,
            'external_message_id' => $data->externalMessageId,
            'direction' => $data->direction,
            'sender_kind' => $data->senderKind,
            'sender_name' => $data->senderName,
            'body' => $data->body,
            'attachments' => $data->attachments ?: null,
            'delivery_status' => Message::DELIVERY_SENT,
            'is_auto_reply' => false,
            'sent_at' => $data->sentAt,
            'read_at' => $data->isRead ? ($data->sentAt ?? now()) : null,
            'raw' => $data->raw,
        ]);

        return true;
    }

    /**
     * last_message_*, and the status transitions:
     *   new thread            -> pending if the buyer wrote last, otherwise awaiting_buyer
     *   new inbound message   -> pending (this also reopens a resolved thread)
     *   answered elsewhere    -> a pending thread whose latest message is ours becomes awaiting_buyer
     *   otherwise             -> unchanged (a resolved thread stays resolved)
     */
    private function refreshDerivedFields(MessageThread $thread, bool $isNew, bool $newInbound): void
    {
        $latest = $thread->messages()
            ->where('direction', '!=', Message::DIRECTION_INTERNAL_NOTE)
            ->where(fn ($q) => $q->whereNull('delivery_status')->orWhere('delivery_status', '!=', Message::DELIVERY_FAILED))
            // An automatic "we are offline" answer is not a team reply: the buyer still needs a person.
            ->where(fn ($q) => $q->whereNull('is_auto_reply')->orWhere('is_auto_reply', false))
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->first();

        if ($latest) {
            $thread->last_message_at = $latest->sent_at ?? $thread->external_updated_at;
            $thread->last_message_preview = mb_substr(trim((string) preg_replace('/\s+/', ' ', $latest->body)), 0, self::PREVIEW_LENGTH);
            $thread->last_message_direction = $latest->direction;

            if ($isNew) {
                $thread->status = $latest->direction === Message::DIRECTION_INBOUND
                    ? MessageThread::STATUS_PENDING
                    : MessageThread::STATUS_AWAITING_BUYER;
            } elseif ($newInbound) {
                $thread->status = MessageThread::STATUS_PENDING;
            } elseif ($latest->direction === Message::DIRECTION_OUTBOUND && $thread->status === MessageThread::STATUS_PENDING) {
                $thread->status = MessageThread::STATUS_AWAITING_BUYER;
            }
        } else {
            $thread->last_message_at ??= $thread->external_updated_at;
        }

        $thread->save();
    }

    /**
     * Keep a kind the linker already decided (pre/post purchase); take the driver's otherwise.
     */
    private function keepOrUse(?string $current, string $fromDriver): string
    {
        return $current && $current !== MessageThread::KIND_UNKNOWN ? $current : $fromDriver;
    }
}
