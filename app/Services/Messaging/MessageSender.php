<?php

namespace App\Services\Messaging;

use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Messaging\Contracts\MessageChannelDriver;
use App\Services\Messaging\Data\SendMessageData;
use App\Services\Messaging\Exceptions\MessagingException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Everything staff do to a conversation: reply to the buyer, leave internal notes,
 * change status, assign, and mark read/unread. Channel-agnostic: it only talks to the
 * channel's MessageChannelDriver.
 *
 * Replies are sent synchronously (the scheduler only drains the queue every few minutes,
 * so a queued reply would sit for minutes). A failed send is kept with its error and can be retried.
 */
class MessageSender
{
    public const MAX_LENGTH = 2000;

    public function __construct(
        private MessageDriverManager $drivers,
        private AuditLogger $audit,
    ) {}

    /**
     * Send a reply to the buyer. Returns the stored message (delivery_status says whether it went out).
     *
     * @throws MessagingException when the thread cannot be replied to at all (nothing is stored)
     */
    public function reply(MessageThread $thread, User $user, string $body): Message
    {
        $body = $this->cleanBody($body);
        $this->assertCanReply($thread);

        return $this->withThreadLock($thread, function () use ($thread, $user, $body) {
            $message = Message::create([
                'thread_id' => $thread->id,
                'direction' => Message::DIRECTION_OUTBOUND,
                'sender_kind' => Message::SENDER_SELLER,
                'sender_name' => $user->name,
                'sent_by_user_id' => $user->id,
                'body' => $body,
                'delivery_status' => Message::DELIVERY_FAILED,
                'is_auto_reply' => false,
                'sent_at' => now(),
            ]);

            return $this->deliver($thread, $message, $user);
        });
    }

    /**
     * Try again to send a message whose first attempt failed.
     */
    public function retry(Message $message, User $user): Message
    {
        $thread = $message->thread;

        if ($message->direction !== Message::DIRECTION_OUTBOUND || $message->delivery_status !== Message::DELIVERY_FAILED) {
            throw new MessagingException('Only a message that failed to send can be retried.');
        }
        $this->assertCanReply($thread);

        return $this->withThreadLock($thread, fn () => $this->deliver($thread, $message, $user));
    }

    /**
     * Private note for the team. Never sent to the platform, never changes the status.
     */
    public function note(MessageThread $thread, User $user, string $body): Message
    {
        $message = Message::create([
            'thread_id' => $thread->id,
            'direction' => Message::DIRECTION_INTERNAL_NOTE,
            'sender_kind' => Message::SENDER_TEAM,
            'sender_name' => $user->name,
            'sent_by_user_id' => $user->id,
            'body' => $this->cleanBody($body),
            'delivery_status' => Message::DELIVERY_SENT,
            'is_auto_reply' => false,
            'sent_at' => now(),
        ]);

        $this->log('message_note_added', $thread, [], ['message_id' => $message->id]);

        return $message;
    }

    public function setStatus(MessageThread $thread, string $status): MessageThread
    {
        $old = $thread->status;
        $thread->update(['status' => $status]);

        if ($old !== $status) {
            $this->log('message_status_changed', $thread, ['status' => $old], ['status' => $status]);
        }

        return $thread;
    }

    public function assign(MessageThread $thread, ?User $assignee): MessageThread
    {
        $old = $thread->assigned_user_id;
        $thread->update(['assigned_user_id' => $assignee?->id]);

        if ($old !== $assignee?->id) {
            $this->log('message_assigned', $thread, ['assigned_user_id' => $old], ['assigned_user_id' => $assignee?->id]);
        }

        return $thread;
    }

    /**
     * Mark a conversation read or unread. Unread is kept only if the platform accepted it, because the
     * next sync would otherwise overwrite our local flag with the platform's. Marking read always
     * clears our copy; a platform failure is reported through the return value, not thrown.
     *
     * @return string|null a warning to show, or null when everything worked
     *
     * @throws MessagingException when marking unread could not be done on the platform
     */
    public function setRead(MessageThread $thread, bool $read): ?string
    {
        $driver = $this->drivers->forChannel($thread->salesChannel);
        $warning = null;

        if (in_array(MessageChannelDriver::CAP_MARK_READ, $driver->capabilities(), true) && !$thread->isPlatformSystem()) {
            try {
                $driver->markRead($thread->salesChannel, $thread, $read);
            } catch (MessagingException $e) {
                if (!$read) {
                    throw new MessagingException('Could not mark this conversation unread on the sales channel: ' . $e->getMessage(), previous: $e);
                }
                $warning = 'Marked read here, but the sales channel could not be updated: ' . $e->getMessage();
                Log::channel('messages')->warning('Mark-read failed on the platform', ['thread_id' => $thread->id, 'error' => $e->getMessage()]);
            }
        } elseif (!$read) {
            throw new MessagingException('This sales channel does not support marking conversations unread.');
        }

        DB::transaction(function () use ($thread, $read) {
            if ($read) {
                $thread->messages()->where('direction', Message::DIRECTION_INBOUND)->whereNull('read_at')->update(['read_at' => now()]);
                $thread->update(['unread_count' => 0]);
            } else {
                $thread->update(['unread_count' => max(1, (int) $thread->unread_count)]);
            }
        });

        return $warning;
    }

    // ------------------------------------------------------------------ internals

    private function deliver(MessageThread $thread, Message $message, User $user): Message
    {
        $channel = $thread->salesChannel;
        $driver = $this->drivers->forChannel($channel);

        try {
            $sent = $driver->send($channel, new SendMessageData(
                body: $message->body,
                externalThreadId: $thread->external_thread_id,
            ));
        } catch (MessagingException $e) {
            $message->update(['delivery_status' => Message::DELIVERY_FAILED, 'error' => mb_substr($e->getMessage(), 0, 1000)]);
            Log::channel('messages')->warning('Reply could not be sent', ['thread_id' => $thread->id, 'error' => $e->getMessage()]);

            return $message->refresh();
        }

        $message->forceFill([
            'external_message_id' => $sent->externalMessageId,
            'delivery_status' => Message::DELIVERY_SENT,
            'error' => null,
            'sent_at' => $sent->sentAt ?? now(),
            'raw' => $sent->raw ?: null,
        ])->save();

        DB::transaction(function () use ($thread, $message, $user) {
            $thread->messages()->where('direction', Message::DIRECTION_INBOUND)->whereNull('read_at')->update(['read_at' => now()]);

            $thread->forceFill([
                'status' => MessageThread::STATUS_AWAITING_BUYER,
                'unread_count' => 0,
                'last_message_at' => $message->sent_at,
                'last_message_preview' => mb_substr(trim((string) preg_replace('/\s+/', ' ', $message->body)), 0, 160),
                'last_message_direction' => Message::DIRECTION_OUTBOUND,
                // Whoever answers an unassigned conversation takes it.
                'assigned_user_id' => $thread->assigned_user_id ?? $user->id,
            ])->save();
        });

        $this->log('message_replied', $thread, [], ['message_id' => $message->id, 'length' => mb_strlen($message->body)]);

        return $message->refresh();
    }

    private function assertCanReply(MessageThread $thread): void
    {
        if ($thread->isPlatformSystem()) {
            throw new MessagingException('Platform notices cannot be replied to.');
        }

        $driver = $this->drivers->forChannel($thread->salesChannel);
        if (!in_array(MessageChannelDriver::CAP_SEND, $driver->capabilities(), true)) {
            throw new MessagingException('This sales channel does not support replying from the inbox.');
        }
    }

    private function cleanBody(string $body): string
    {
        $body = trim(str_replace("\r\n", "\n", $body));

        if ($body === '') {
            throw new MessagingException('Write a message first.');
        }
        if (mb_strlen($body) > self::MAX_LENGTH) {
            throw new MessagingException('Messages can be at most ' . self::MAX_LENGTH . ' characters.');
        }

        return $body;
    }

    /**
     * One send per conversation at a time, so a double click cannot reach the buyer twice.
     */
    private function withThreadLock(MessageThread $thread, callable $callback): mixed
    {
        $lock = Cache::lock('message-thread-send:' . $thread->id, 30);

        try {
            $lock->block(5);
        } catch (LockTimeoutException) {
            throw new MessagingException('Another reply to this conversation is still being sent. Try again in a moment.');
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    private function log(string $event, MessageThread $thread, array $old, array $new): void
    {
        try {
            $this->audit->log($event, $thread, $old, $new);
        } catch (\Throwable $e) {
            Log::channel('messages')->warning('Audit entry for a message action failed', ['event' => $event, 'error' => $e->getMessage()]);
        }
    }
}
