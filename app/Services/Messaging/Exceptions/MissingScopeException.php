<?php

namespace App\Services\Messaging\Exceptions;

/**
 * The store's token lacks the messaging permission. The fix is for the seller to
 * re-authorize the store; the UI shows a "re-authorize" prompt for this error.
 */
class MissingScopeException extends MessagingException
{
}
