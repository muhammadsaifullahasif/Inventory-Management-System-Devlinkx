<?php

namespace App\Services\Messaging;

use App\Models\Message;
use App\Models\MessageChannelState;
use App\Models\MessageSetting;
use App\Models\MessageThread;
use App\Services\Messaging\Contracts\MessageChannelDriver;
use App\Services\Messaging\Data\SendMessageData;
use App\Services\Messaging\Exceptions\MessagingException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Sends the "we are offline" answer to a buyer who writes outside office hours.
 *
 * This really sends a message to a customer, so it is deliberately cautious. It replies only when ALL hold:
 *   - auto-reply is on globally AND for this store, and the reply text is not empty
 *   - the thread is a pre- or post-purchase conversation (never platform notices or unclassified threads)
 *   - the newest real message in the thread is a fresh message FROM THE BUYER (so never to the seller,
 *     the platform, or an earlier auto-reply, and never when the team has already answered)
 *   - the buyer wrote while the team was offline
 *   - this thread was not auto-answered within the cooldown, and not since that message
 *
 * A send failure is logged and never thrown: the sync that triggered this must not fail because of it,
 * and a failed attempt does not use up the cooldown.
 */
class AutoReplyService
{
    /** A message older than this is history (backfill, an outage) and is not answered automatically. */
    public const MAX_AGE_MINUTES = 120;

    public const SENDER_NAME = 'Automatic reply';

    public function __construct(private MessageDriverManager $drivers) {}

    /**
     * @return Message|null the stored auto-reply, or null when nothing was sent
     */
    public function maybeReply(MessageThread $thread): ?Message
    {
        $settings = MessageSetting::current();

        if (!$settings->auto_reply_enabled || trim((string) $settings->auto_reply_text) === '') {
            return null;
        }

        $thread->loadMissing('salesChannel');
        $channel = $thread->salesChannel;
        if (!$channel) {
            return null;
        }

        $state = MessageChannelState::forChannel($channel);
        if (!$state->messaging_enabled || !$state->auto_reply_enabled) {
            return null;
        }

        $inbound = $this->latestRealMessage($thread);

        if ($reason = $this->skipReason($thread, $inbound, $settings)) {
            Log::channel('messages')->info('No automatic reply', ['thread_id' => $thread->id, 'reason' => $reason]);
            return null;
        }

        $driver = $this->drivers->forChannel($channel);
        if (!in_array(MessageChannelDriver::CAP_SEND, $driver->capabilities(), true)) {
            return null;
        }

        $lock = Cache::lock('message-auto-reply:' . $thread->id, 60);
        if (!$lock->get()) {
            return null; // another process is answering this thread right now
        }

        try {
            // Re-read under the lock: the other process may have answered while we were deciding.
            $fresh = MessageThread::query()->find($thread->id);
            if (!$fresh || $this->skipReason($fresh, $inbound, $settings)) {
                return null;
            }

            $previous = $fresh->auto_replied_at;
            // Claim first, so a crash or timeout after sending can never lead to a second automatic message.
            $fresh->forceFill(['auto_replied_at' => now()])->save();

            try {
                $sent = $driver->send($channel, new SendMessageData(
                    body: $this->render((string) $settings->auto_reply_text, $thread),
                    externalThreadId: $thread->external_thread_id,
                ));
            } catch (MessagingException $e) {
                $fresh->forceFill(['auto_replied_at' => $previous])->save();
                Log::channel('messages')->warning('Automatic reply could not be sent', ['thread_id' => $thread->id, 'error' => $e->getMessage()]);
                return null;
            }

            $thread->auto_replied_at = $fresh->auto_replied_at;

            $message = Message::create([
                'thread_id' => $thread->id,
                'external_message_id' => $sent->externalMessageId,
                'direction' => Message::DIRECTION_OUTBOUND,
                'sender_kind' => Message::SENDER_AUTO_REPLY,
                'sender_name' => self::SENDER_NAME,
                'body' => $this->render((string) $settings->auto_reply_text, $thread),
                'delivery_status' => Message::DELIVERY_SENT,
                'is_auto_reply' => true,
                'sent_at' => $sent->sentAt ?? now(),
                'raw' => $sent->raw ?: null,
            ]);

            Log::channel('messages')->info('Automatic reply sent', ['thread_id' => $thread->id, 'message_id' => $message->id]);

            return $message;
        } catch (\Throwable $e) {
            Log::channel('messages')->error('Automatic reply failed', ['thread_id' => $thread->id, 'error' => $e->getMessage()]);
            return null;
        } finally {
            $lock->release();
        }
    }

    /**
     * Why NOT to answer this buyer message automatically, or null when it should be answered.
     */
    private function skipReason(MessageThread $thread, ?Message $inbound, MessageSetting $settings): ?string
    {
        if (!in_array($thread->thread_kind, [MessageThread::KIND_PRE_PURCHASE, MessageThread::KIND_POST_PURCHASE], true)) {
            return 'not a buyer conversation (' . ($thread->thread_kind ?: 'unknown') . ')';
        }

        if (!$inbound || $inbound->direction !== Message::DIRECTION_INBOUND || $inbound->sender_kind !== Message::SENDER_BUYER) {
            return 'the latest message is not from the buyer';
        }

        if (!$inbound->sent_at || $inbound->sent_at->lt(now()->subMinutes(self::MAX_AGE_MINUTES))) {
            return 'the buyer message is too old to answer automatically';
        }

        if ((new OfficeHours($settings))->isOpen($inbound->sent_at)) {
            return 'the team was online when the buyer wrote';
        }

        if ($thread->auto_replied_at) {
            if ($thread->auto_replied_at->gte($inbound->sent_at)) {
                return 'this message was already answered automatically';
            }
            $cooldown = max(1, (int) $settings->auto_reply_cooldown_hours);
            if ($thread->auto_replied_at->gt(now()->subHours($cooldown))) {
                return "an automatic reply was already sent within the last {$cooldown} hours";
            }
        }

        return null;
    }

    /**
     * Newest message that counts as part of the conversation: no internal notes, no automatic replies,
     * no sends that failed.
     */
    private function latestRealMessage(MessageThread $thread): ?Message
    {
        return $thread->messages()
            ->where('direction', '!=', Message::DIRECTION_INTERNAL_NOTE)
            ->where(fn ($q) => $q->whereNull('is_auto_reply')->orWhere('is_auto_reply', false))
            ->where(fn ($q) => $q->whereNull('delivery_status')->orWhere('delivery_status', '!=', Message::DELIVERY_FAILED))
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Only {buyer_name} is filled in; it falls back to "there" so a buyer never sees a raw placeholder.
     */
    private function render(string $text, MessageThread $thread): string
    {
        $name = trim((string) ($thread->buyer_name ?: $thread->buyer_username));

        return trim(strtr($text, ['{buyer_name}' => $name !== '' ? $name : 'there']));
    }
}
