<?php

namespace App\Services\Messaging\Exceptions;

use RuntimeException;

/**
 * Base class for any messaging failure raised by a channel driver.
 */
class MessagingException extends RuntimeException
{
    /**
     * @param array<string,mixed> $context  Extra detail (status, platform error body) for logs
     */
    public function __construct(
        string $message = '',
        public readonly array $context = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
