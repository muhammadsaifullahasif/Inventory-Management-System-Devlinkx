<?php

namespace App\Services\Messaging\Data;

/**
 * A message to send. Reply in an existing thread (externalThreadId) or start one
 * (recipientUsername, optionally tied to a listing through referenceType/referenceId).
 */
final class SendMessageData
{
    /**
     * @param array<int, array<string,mixed>> $attachments
     */
    public function __construct(
        public readonly string $body,
        public readonly ?string $externalThreadId = null,
        public readonly ?string $recipientUsername = null,
        public readonly ?string $referenceType = null,
        public readonly ?string $externalReferenceId = null,
        public readonly array $attachments = [],
    ) {}
}
