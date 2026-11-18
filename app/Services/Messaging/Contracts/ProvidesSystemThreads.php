<?php

namespace App\Services\Messaging\Contracts;

/**
 * Optional capability: a platform that also has system-generated conversations
 * (eBay payout and return notices) which are skipped unless asked for.
 */
interface ProvidesSystemThreads
{
    public function withSystemThreads(bool $include = true): static;
}
