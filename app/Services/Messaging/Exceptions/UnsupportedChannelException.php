<?php

namespace App\Services\Messaging\Exceptions;

/**
 * No messaging driver is registered for the channel's type.
 */
class UnsupportedChannelException extends MessagingException
{
}
