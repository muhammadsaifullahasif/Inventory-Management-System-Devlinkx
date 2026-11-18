<?php

namespace App\Services\Messaging\Contracts;

use App\Models\MessageThread;
use App\Models\SalesChannel;
use App\Services\Messaging\Data\SendMessageData;
use App\Services\Messaging\Data\SentMessageData;
use App\Services\Messaging\Data\ThreadData;
use App\Services\Messaging\Exceptions\MessagingException;
use Carbon\CarbonInterface;

/**
 * One implementation per sales-channel type (eBay, WooCommerce, Shopify, ...).
 * The inbox, sync, UI and auto-reply code depend only on this contract and the
 * normalized Data objects, never on platform payloads.
 */
interface MessageChannelDriver
{
    /** Capability flags returned by capabilities(). */
    public const CAP_SEND = 'send';
    public const CAP_ATTACHMENTS = 'attachments';
    public const CAP_MARK_READ = 'mark_read';
    public const CAP_START_THREAD = 'start_thread';

    /**
     * What this channel can do, so the UI can hide unsupported actions.
     *
     * @return array<int,string> CAP_* values
     */
    public function capabilities(): array;

    /**
     * Threads changed since $cursor (null = full backfill), as summaries.
     * Messages inside may be only the latest one (see ThreadData::$hasFullHistory).
     * $cursor is opaque and driver-defined; the sync service just stores and returns it.
     *
     * @return iterable<int,ThreadData>
     * @throws MessagingException
     */
    public function fetchThreads(SalesChannel $channel, ?string $cursor = null): iterable;

    /**
     * The complete message history of one thread.
     *
     * @throws MessagingException
     */
    public function fetchThread(SalesChannel $channel, MessageThread $thread): ThreadData;

    /**
     * Send a message to a buyer.
     *
     * @throws MessagingException
     */
    public function send(SalesChannel $channel, SendMessageData $message): SentMessageData;

    /**
     * Mark a thread read or unread on the platform. No-op when unsupported.
     *
     * @throws MessagingException
     */
    public function markRead(SalesChannel $channel, MessageThread $thread, bool $read): void;

    /**
     * The next sync cursor after a successful pass (e.g. the pass's start time).
     * Call after fully iterating fetchThreads().
     */
    public function nextCursor(): string;

    /**
     * A cursor meaning "everything changed since $time", for backfills and `--from`.
     * Keeps the cursor format private to the driver.
     */
    public function cursorFromTime(CarbonInterface $time): string;
}
