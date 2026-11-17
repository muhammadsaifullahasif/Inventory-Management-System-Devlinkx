<?php

namespace App\Services\Messaging\Data;

use Carbon\CarbonInterface;

/**
 * One message, normalized. Every channel driver returns these, never raw payloads.
 */
final class MessageData
{
    /**
     * @param string                          $direction        Message::DIRECTION_INBOUND|OUTBOUND
     * @param string                          $senderKind       Message::SENDER_*
     * @param array<int, array<string,mixed>> $attachments      [['name'=>, 'type'=>, 'url'=>], ...]
     * @param array<string,mixed>             $raw              Original platform payload, kept for debugging
     */
    public function __construct(
        public readonly string $externalMessageId,
        public readonly string $direction,
        public readonly string $senderKind,
        public readonly ?string $senderName,
        public readonly string $body,
        public readonly ?CarbonInterface $sentAt,
        public readonly bool $isRead = false,
        public readonly array $attachments = [],
        public readonly array $raw = [],
    ) {}
}
