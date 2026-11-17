<?php

namespace App\Services\Messaging\Drivers;

use App\Models\Message;
use App\Models\MessageThread;
use App\Models\SalesChannel;
use App\Services\Ebay\EbayMessageApiClient;
use App\Services\Messaging\Contracts\MessageChannelDriver;
use App\Services\Messaging\Data\MessageData;
use App\Services\Messaging\Data\SendMessageData;
use App\Services\Messaging\Data\SentMessageData;
use App\Services\Messaging\Data\ThreadData;
use App\Services\Messaging\Exceptions\MessagingException;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * eBay implementation of the messaging contract (Commerce Message API).
 * All eBay-specific names and shapes stay in this class and EbayMessageApiClient.
 *
 * NOTE: the JSON field names read below (conversationId, latestMessage, messageBody,
 * senderUsername, readStatus, ...) follow eBay's docs but are unverified against live
 * data; the mapping is deliberately tolerant. Re-check it against the Phase 0 captures.
 */
class EbayMessageDriver implements MessageChannelDriver
{
    private const PAGE_SIZE = 50;
    private const MAX_PAGES = 100; // safety stop against runaway pagination
    private const CURSOR_OVERLAP_SECONDS = 120; // re-read a little to avoid gaps between passes

    private ?CarbonInterface $passStartedAt = null;

    public function __construct(
        private EbayMessageApiClient $api,
    ) {}

    public function capabilities(): array
    {
        return [
            self::CAP_SEND,
            self::CAP_MARK_READ,
            self::CAP_START_THREAD,
        ];
    }

    public function fetchThreads(SalesChannel $channel, ?string $cursor = null): iterable
    {
        $this->passStartedAt = now();

        $filters = [];
        if ($cursor) {
            $filters['start_time'] = $this->parseTime($cursor)?->utc()->format('Y-m-d\TH:i:s.000\Z');
        }

        $own = $this->ownUsernames($channel);

        foreach ([EbayMessageApiClient::TYPE_FROM_MEMBERS, EbayMessageApiClient::TYPE_FROM_EBAY] as $type) {
            for ($page = 0, $offset = 0; $page < self::MAX_PAGES; $page++, $offset += self::PAGE_SIZE) {
                $body = $this->api->getConversations($channel, $type, $filters, self::PAGE_SIZE, $offset);
                $conversations = $body['conversations'] ?? [];

                foreach ($conversations as $conversation) {
                    yield $this->mapConversation($conversation, $type, $own);
                }

                if (count($conversations) < self::PAGE_SIZE) {
                    break;
                }
            }
        }
    }

    public function fetchThread(SalesChannel $channel, MessageThread $thread): ThreadData
    {
        $type = $this->typeFor($thread);
        $own = $this->ownUsernames($channel);

        $conversation = [];
        $messages = [];

        for ($page = 0, $offset = 0; $page < self::MAX_PAGES; $page++, $offset += self::PAGE_SIZE) {
            $body = $this->api->getConversation($channel, $thread->external_thread_id, $type, self::PAGE_SIZE, $offset);
            $conversation = $conversation ?: $body;
            $batch = $body['messages'] ?? [];

            foreach ($batch as $raw) {
                $messages[] = $this->mapMessage($raw, $type, $own);
            }

            if (count($batch) < self::PAGE_SIZE) {
                break;
            }
        }

        usort($messages, fn (MessageData $a, MessageData $b) => ($a->sentAt?->getTimestamp() ?? 0) <=> ($b->sentAt?->getTimestamp() ?? 0));

        $conversation['conversationId'] = $conversation['conversationId'] ?? $thread->external_thread_id;

        return $this->buildThread($conversation, $type, $own, $messages, fullHistory: true);
    }

    public function send(SalesChannel $channel, SendMessageData $message): SentMessageData
    {
        if (!$message->externalThreadId && !$message->recipientUsername) {
            throw new MessagingException('A thread or a recipient is required to send a message.');
        }

        $payload = ['messageText' => $message->body];

        if ($message->externalThreadId) {
            $payload['conversationId'] = $message->externalThreadId;
        } else {
            $payload['otherPartyUsername'] = $message->recipientUsername;
        }

        if ($message->referenceType && $message->externalReferenceId) {
            $payload['reference'] = [
                'referenceType' => $message->referenceType,
                'referenceId' => $message->externalReferenceId,
            ];
        }

        if ($message->attachments) {
            $payload['messageMedia'] = array_map(fn ($a) => [
                'mediaName' => $a['name'] ?? null,
                'mediaType' => $a['type'] ?? null,
                'mediaUrl' => $a['url'] ?? null,
            ], $message->attachments);
        }

        $body = $this->api->sendMessage($channel, $payload);

        return new SentMessageData(
            externalMessageId: isset($body['messageId']) ? (string) $body['messageId'] : null,
            externalThreadId: isset($body['conversationId']) ? (string) $body['conversationId'] : $message->externalThreadId,
            sentAt: $this->parseTime($body['createdDate'] ?? null) ?? now(),
            raw: $body,
        );
    }

    public function markRead(SalesChannel $channel, MessageThread $thread, bool $read): void
    {
        $this->api->updateConversation($channel, [
            'conversationId' => $thread->external_thread_id,
            'conversationType' => $this->typeFor($thread),
            'read' => $read,
        ]);
    }

    public function nextCursor(): string
    {
        $start = $this->passStartedAt ?? now();

        return $start->copy()->subSeconds(self::CURSOR_OVERLAP_SECONDS)->utc()->toIso8601String();
    }

    // ---------------------------------------------------------------
    // Mapping eBay payloads -> normalized data
    // ---------------------------------------------------------------

    /**
     * @param array<string,mixed> $conversation
     * @param array<int,string>   $own
     */
    private function mapConversation(array $conversation, string $type, array $own): ThreadData
    {
        $latest = $conversation['latestMessage'] ?? null;
        $messages = is_array($latest) && $latest ? [$this->mapMessage($latest, $type, $own)] : [];

        return $this->buildThread($conversation, $type, $own, $messages, fullHistory: false);
    }

    /**
     * @param array<string,mixed>    $conversation
     * @param array<int,string>      $own
     * @param array<int,MessageData> $messages
     */
    private function buildThread(array $conversation, string $type, array $own, array $messages, bool $fullHistory): ThreadData
    {
        $buyer = $this->findBuyerUsername($conversation, $messages, $type, $own);
        $latestAt = null;
        foreach ($messages as $m) {
            if ($m->sentAt && (!$latestAt || $m->sentAt->gt($latestAt))) {
                $latestAt = $m->sentAt;
            }
        }

        $subject = $conversation['conversationTitle']
            ?? ($conversation['latestMessage']['subject'] ?? null);

        return new ThreadData(
            externalThreadId: (string) ($conversation['conversationId'] ?? ''),
            threadKind: $type === EbayMessageApiClient::TYPE_FROM_EBAY
                ? MessageThread::KIND_PLATFORM_SYSTEM
                : MessageThread::KIND_UNKNOWN, // pre/post-purchase is decided later by the order linker
            buyerUsername: $buyer,
            subject: $subject !== null ? (string) $subject : null,
            referenceType: isset($conversation['referenceType']) ? (string) $conversation['referenceType'] : null,
            externalReferenceId: isset($conversation['referenceId']) ? (string) $conversation['referenceId'] : null,
            updatedAt: $latestAt ?? $this->parseTime($conversation['createdDate'] ?? null),
            unreadCount: (int) ($conversation['unreadCount'] ?? 0),
            messages: $messages,
            hasFullHistory: $fullHistory,
            raw: $conversation,
        );
    }

    /**
     * @param array<string,mixed> $raw
     * @param array<int,string>   $own  lowercase usernames of the store itself
     */
    private function mapMessage(array $raw, string $type, array $own): MessageData
    {
        $sender = isset($raw['senderUsername']) ? (string) $raw['senderUsername'] : null;
        $fromSeller = $sender !== null && in_array(strtolower($sender), $own, true);
        $fromPlatform = !$fromSeller && (
            $type === EbayMessageApiClient::TYPE_FROM_EBAY
            || ($sender !== null && strtolower($sender) === 'ebay')
        );

        return new MessageData(
            externalMessageId: (string) ($raw['messageId'] ?? ''),
            direction: $fromSeller ? Message::DIRECTION_OUTBOUND : Message::DIRECTION_INBOUND,
            senderKind: $fromSeller ? Message::SENDER_SELLER : ($fromPlatform ? Message::SENDER_PLATFORM : Message::SENDER_BUYER),
            senderName: $sender,
            body: (string) ($raw['messageBody'] ?? ''),
            sentAt: $this->parseTime($raw['createdDate'] ?? null),
            isRead: $fromSeller || filter_var($raw['readStatus'] ?? false, FILTER_VALIDATE_BOOLEAN),
            attachments: array_values(array_map(fn ($m) => [
                'name' => $m['mediaName'] ?? null,
                'type' => $m['mediaType'] ?? null,
                'url' => $m['mediaUrl'] ?? null,
            ], is_array($raw['messageMedia'] ?? null) ? $raw['messageMedia'] : [])),
            raw: $raw,
        );
    }

    /**
     * The buyer is whichever party in the messages is not the store itself.
     *
     * @param array<string,mixed>    $conversation
     * @param array<int,MessageData> $messages
     * @param array<int,string>      $own
     */
    private function findBuyerUsername(array $conversation, array $messages, string $type, array $own): ?string
    {
        if ($type === EbayMessageApiClient::TYPE_FROM_EBAY) {
            return null; // system conversations have no buyer
        }

        $candidates = [];
        foreach ($messages as $m) {
            $candidates[] = $m->raw['senderUsername'] ?? null;
            $candidates[] = $m->raw['recipientUsername'] ?? null;
        }
        $candidates[] = $conversation['latestMessage']['senderUsername'] ?? null;
        $candidates[] = $conversation['latestMessage']['recipientUsername'] ?? null;

        foreach ($candidates as $name) {
            if ($name && !in_array(strtolower((string) $name), $own, true) && strtolower((string) $name) !== 'ebay') {
                return (string) $name;
            }
        }

        return null;
    }

    private function typeFor(MessageThread $thread): string
    {
        return $thread->thread_kind === MessageThread::KIND_PLATFORM_SYSTEM
            ? EbayMessageApiClient::TYPE_FROM_EBAY
            : EbayMessageApiClient::TYPE_FROM_MEMBERS;
    }

    /**
     * The store's own eBay usernames, lowercased, used to tell seller messages from buyer ones.
     *
     * @return array<int,string>
     */
    private function ownUsernames(SalesChannel $channel): array
    {
        $names = (array) ($channel->external_account_ids ?? []);
        if ($channel->external_account_id) {
            $names[] = $channel->external_account_id;
        }

        return array_values(array_unique(array_map(fn ($n) => strtolower((string) $n), array_filter($names))));
    }

    private function parseTime(mixed $value): ?CarbonInterface
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
