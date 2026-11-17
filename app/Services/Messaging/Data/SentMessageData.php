<?php

namespace App\Services\Messaging\Data;

use Carbon\CarbonInterface;

/**
 * What the platform returned after accepting a message.
 */
final class SentMessageData
{
    /**
     * @param array<string,mixed> $raw
     */
    public function __construct(
        public readonly ?string $externalMessageId,
        public readonly ?string $externalThreadId,
        public readonly ?CarbonInterface $sentAt,
        public readonly array $raw = [],
    ) {}
}
