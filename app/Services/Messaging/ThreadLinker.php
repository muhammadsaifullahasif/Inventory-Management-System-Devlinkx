<?php

namespace App\Services\Messaging;

use App\Models\MessageThread;
use App\Models\Order;
use App\Models\SalesChannel;
use App\Models\SalesChannelProduct;

/**
 * Attaches a buyer thread to the product it asks about and to the buyer's order,
 * and decides whether it is a pre-purchase or post-purchase conversation.
 *
 *  - product: thread's listing id -> sales_channel_product.external_listing_id -> product
 *  - order:   same store + same buyer username + an order item for that listing
 *
 * Deliberately conservative: without a listing id it never guesses an order.
 * Channel-agnostic: it only reads the generic thread fields.
 */
class ThreadLinker
{
    /**
     * Link one thread. Saves only when something changed.
     */
    public function link(MessageThread $thread): MessageThread
    {
        $reference = $thread->external_reference_id;

        if (!$thread->product_id && $reference) {
            $productId = SalesChannelProduct::query()
                ->where('sales_channel_id', $thread->sales_channel_id)
                ->where('external_listing_id', $reference)
                ->value('product_id');

            if ($productId) {
                $thread->product_id = $productId;
            }
        }

        if ($thread->isPlatformSystem()) {
            return $this->saveIfDirty($thread);
        }

        if (!$thread->order_id && $reference && $thread->buyer_username) {
            $orderId = Order::query()
                ->where('sales_channel_id', (string) $thread->sales_channel_id)
                ->whereRaw('LOWER(buyer_username) = ?', [mb_strtolower($thread->buyer_username)])
                ->whereHas('items', fn ($q) => $q->where('channel_item_id', $reference))
                ->orderByDesc('order_date')
                ->value('id');

            if ($orderId) {
                $thread->order_id = $orderId;
            }
        }

        $thread->thread_kind = $thread->order_id
            ? MessageThread::KIND_POST_PURCHASE
            : MessageThread::KIND_PRE_PURCHASE;

        return $this->saveIfDirty($thread);
    }

    /**
     * Re-try threads that have no order yet, e.g. a question asked before the buyer ordered.
     * Run after each sync so new orders attach to their earlier conversations.
     *
     * @return int Threads that gained an order link
     */
    public function relinkUnlinked(SalesChannel $channel, int $limit = 500): int
    {
        $linked = 0;

        MessageThread::query()
            ->where('sales_channel_id', $channel->id)
            ->where('thread_kind', '!=', MessageThread::KIND_PLATFORM_SYSTEM)
            ->whereNull('order_id')
            ->whereNotNull('external_reference_id')
            ->whereNotNull('buyer_username')
            ->orderByDesc('last_message_at')
            ->limit($limit)
            ->get()
            ->each(function (MessageThread $thread) use (&$linked) {
                if ($this->link($thread)->order_id) {
                    $linked++;
                }
            });

        return $linked;
    }

    private function saveIfDirty(MessageThread $thread): MessageThread
    {
        if ($thread->isDirty()) {
            $thread->save();
        }

        return $thread;
    }
}
