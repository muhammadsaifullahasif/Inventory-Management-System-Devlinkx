<?php

namespace App\Services\Messaging;

use App\Models\MessageThread;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SalesChannel;
use App\Models\SalesChannelProduct;
use Illuminate\Support\Facades\DB;

/**
 * Attaches a buyer thread to the product it asks about and to the buyer's order,
 * and decides whether it is a pre-purchase or post-purchase conversation.
 *
 *  - product: the thread's listing id -> sales_channel_product, else the listing's SKU -> products.sku
 *             (see findProductId() for the full order)
 *  - order:   same store + same buyer username + an order item for that listing
 *
 * Deliberately conservative: without a listing id it never guesses an order.
 * Channel-agnostic: it only reads the generic thread fields.
 */
class ThreadLinker
{
    /** Only threads active within this many days are re-checked for missing links. */
    private const RELINK_WINDOW_DAYS = 14;

    /**
     * Link one thread. Saves only when something changed.
     */
    public function link(MessageThread $thread): MessageThread
    {
        $reference = $thread->external_reference_id;

        if (!$thread->product_id && $reference) {
            $productId = $this->findProductId($thread, $reference);

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
     * Find the catalog product a listing refers to, trying the most reliable source first:
     *
     *  1. the store's own listing record (sales_channel_product.external_listing_id)
     *  2. the listing's SKU, as seen on orders for that listing, matched to products.sku
     *     - plus the listing id itself as a SKU: imports and order sync use the item id as the
     *       product SKU when a listing has no custom SKU
     *  3. the channel_item_id meta written when the product was imported from the channel
     *
     * Read-only: it never creates or edits listing records, only finds the product.
     */
    private function findProductId(MessageThread $thread, string $reference): ?int
    {
        $productId = SalesChannelProduct::query()
            ->where('sales_channel_id', $thread->sales_channel_id)
            ->where('external_listing_id', $reference)
            ->value('product_id');

        if ($productId) {
            return (int) $productId;
        }

        $skus = OrderItem::query()
            ->where('channel_item_id', $reference)
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderByDesc('id')
            ->limit(5)
            ->pluck('sku')
            ->push($reference)
            ->unique();

        foreach ($skus as $sku) {
            $productId = Product::query()->where('sku', $sku)->value('id');

            if ($productId) {
                return (int) $productId;
            }
        }

        $productId = DB::table('product_metas')
            ->where('meta_key', 'channel_item_id')
            ->where('meta_value', $reference)
            ->value('product_id');

        return $productId ? (int) $productId : null;
    }

    /**
     * Re-try recent threads that are still missing a product or an order, e.g. a question asked
     * before the buyer ordered, or a listing imported after the message arrived.
     * Run after each sync. Limited to recent threads so conversations that can never be matched
     * are not re-checked on every run.
     *
     * @return int Threads that gained a product or an order link
     */
    public function relinkUnlinked(SalesChannel $channel, int $limit = 200, int $windowDays = self::RELINK_WINDOW_DAYS): int
    {
        $linked = 0;

        MessageThread::query()
            ->where('sales_channel_id', $channel->id)
            ->whereNotNull('external_reference_id')
            ->where('last_message_at', '>=', now()->subDays($windowDays))
            ->where(function ($q) {
                $q->whereNull('product_id')
                    ->orWhere(function ($q) {
                        $q->whereNull('order_id')
                            ->where('thread_kind', '!=', MessageThread::KIND_PLATFORM_SYSTEM)
                            ->whereNotNull('buyer_username');
                    });
            })
            ->orderByDesc('last_message_at')
            ->limit($limit)
            ->get()
            ->each(function (MessageThread $thread) use (&$linked) {
                $before = [$thread->product_id, $thread->order_id];
                $thread = $this->link($thread);

                if ([$thread->product_id, $thread->order_id] !== $before) {
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
