<?php

namespace App\Services\Messaging\Data;

use Carbon\CarbonInterface;

/**
 * One conversation, normalized.
 *
 * `messages` may hold only the latest message when the data came from a list call;
 * a fetchThread() call returns the complete history. `hasFullHistory` says which.
 */
final class ThreadData
{
    /**
     * @param string                $threadKind  MessageThread::KIND_*
     * @param array<int,MessageData> $messages
     * @param array<string,mixed>   $raw
     */
    public function __construct(
        public readonly string $externalThreadId,
        public readonly string $threadKind,
        public readonly ?string $buyerUsername,
        public readonly ?string $buyerName = null,
        public readonly ?string $buyerExternalId = null,
        public readonly ?string $subject = null,
        public readonly ?string $referenceType = null,
        public readonly ?string $externalReferenceId = null,
        public readonly ?CarbonInterface $updatedAt = null,
        public readonly int $unreadCount = 0,
        public readonly array $messages = [],
        public readonly bool $hasFullHistory = false,
        public readonly array $raw = [],
    ) {}
}
