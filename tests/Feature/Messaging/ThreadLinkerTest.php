<?php

namespace Tests\Feature\Messaging;

use App\Models\MessageThread;
use App\Models\SalesChannel;
use App\Services\Messaging\ThreadLinker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesMessagingTables;
use Tests\TestCase;

/**
 * How a thread's listing id is matched to a catalog product:
 * sales_channel_product first, then the listing's SKU against products.sku.
 */
class ThreadLinkerTest extends TestCase
{
    use CreatesMessagingTables;

    private SalesChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-18 20:00:00');
        $this->createMessagingTables();

        $this->channel = SalesChannel::create(['name' => 'Apex', 'type' => 'ebay', 'user_scopes' => 'x']);

        DB::table('products')->insert([
            ['id' => 1, 'name' => 'Fender (listed in pivot)', 'sku' => 'FENDER-1'],
            ['id' => 2, 'name' => 'Bumper (matched by order SKU)', 'sku' => 'BUMP-2'],
            ['id' => 3, 'name' => 'Mirror (no custom SKU, item id is the SKU)', 'sku' => '555000555'],
            ['id' => 4, 'name' => 'Hood (matched by import meta)', 'sku' => 'HOOD-4'],
            ['id' => 5, 'name' => 'Other product', 'sku' => 'OTHER-5'],
        ]);
    }

    public function test_step_one_the_listing_record_wins(): void
    {
        DB::table('sales_channel_product')->insert(['product_id' => 1, 'sales_channel_id' => $this->channel->id, 'external_listing_id' => '111']);
        // A conflicting SKU hint must not override the listing record.
        $this->orderItem('111', 'BUMP-2');

        $thread = $this->link($this->thread('111'));

        $this->assertSame(1, $thread->product_id);
    }

    public function test_the_listing_record_is_scoped_to_the_store(): void
    {
        $other = SalesChannel::create(['name' => 'Other store', 'type' => 'ebay', 'user_scopes' => 'x']);
        DB::table('sales_channel_product')->insert(['product_id' => 1, 'sales_channel_id' => $other->id, 'external_listing_id' => '111']);

        $this->assertNull($this->link($this->thread('111'))->product_id, "another store's listing record is not used");
    }

    public function test_step_two_sku_seen_on_an_order_for_that_listing_is_matched_to_the_catalog(): void
    {
        $this->orderItem('222', 'BUMP-2');

        $this->assertSame(2, $this->link($this->thread('222'))->product_id);
    }

    public function test_step_two_the_newest_order_sku_that_exists_in_the_catalog_is_used(): void
    {
        $this->orderItem('222', 'UNKNOWN-SKU');   // older, no such product
        $this->orderItem('222', 'BUMP-2');        // newer

        $this->assertSame(2, $this->link($this->thread('222'))->product_id);
    }

    public function test_step_two_a_listing_without_a_custom_sku_uses_the_item_id_as_the_sku(): void
    {
        $this->assertSame(3, $this->link($this->thread('555000555'))->product_id);
    }

    public function test_step_three_import_meta_is_the_last_resort(): void
    {
        DB::table('product_metas')->insert(['product_id' => 4, 'meta_key' => 'channel_item_id', 'meta_value' => '444']);
        DB::table('product_metas')->insert(['product_id' => 5, 'meta_key' => 'weight', 'meta_value' => '444']);   // other key: ignored

        $this->assertSame(4, $this->link($this->thread('444'))->product_id);
    }

    public function test_unmatched_listing_stays_unlinked_without_errors(): void
    {
        $this->orderItem('999', '');            // blank SKU is ignored
        $this->orderItem('999', null);

        $thread = $this->link($this->thread('999'));

        $this->assertNull($thread->product_id);
    }

    public function test_a_thread_without_a_listing_reference_is_never_guessed(): void
    {
        $thread = $this->link($this->thread(null));

        $this->assertNull($thread->product_id);
        $this->assertNull($thread->order_id);
    }

    public function test_relink_picks_up_a_product_that_appears_later_but_skips_old_threads(): void
    {
        $recent = $this->thread('333', lastAt: '2026-11-17 10:00:00');
        $old = $this->thread('333', lastAt: '2026-10-01 10:00:00', externalId: 'old');
        $this->assertNull($recent->fresh()->product_id);

        // The listing is imported (or the product created) after the message arrived.
        DB::table('sales_channel_product')->insert(['product_id' => 1, 'sales_channel_id' => $this->channel->id, 'external_listing_id' => '333']);

        $changed = app(ThreadLinker::class)->relinkUnlinked($this->channel);

        $this->assertSame(1, $changed);
        $this->assertSame(1, $recent->fresh()->product_id);
        $this->assertNull($old->fresh()->product_id, 'older than the 14-day window: not re-checked');
    }

    public function test_relink_command_reaches_older_threads_with_a_wider_window(): void
    {
        $old = $this->thread('333', lastAt: '2026-09-20 10:00:00', externalId: 'old');
        DB::table('sales_channel_product')->insert(['product_id' => 1, 'sales_channel_id' => $this->channel->id, 'external_listing_id' => '333']);

        $this->artisan('messages:relink', ['--days' => 30])->expectsOutputToContain('0 thread(s) newly linked')->assertExitCode(0);
        $this->assertNull($old->fresh()->product_id);

        $this->artisan('messages:relink', ['--days' => 90])->expectsOutputToContain('1 thread(s) newly linked')->assertExitCode(0);
        $this->assertSame(1, $old->fresh()->product_id);

        $this->artisan('messages:relink', ['--days' => 0])->expectsOutputToContain('--days must be 1 or more')->assertExitCode(1);
    }

    public function test_relink_also_covers_system_threads_for_the_product_only(): void
    {
        DB::table('sales_channel_product')->insert(['product_id' => 1, 'sales_channel_id' => $this->channel->id, 'external_listing_id' => '777']);
        $system = $this->thread('777', kind: MessageThread::KIND_PLATFORM_SYSTEM, buyer: null);

        app(ThreadLinker::class)->relinkUnlinked($this->channel);

        $system = $system->fresh();
        $this->assertSame(1, $system->product_id);
        $this->assertNull($system->order_id);
        $this->assertSame(MessageThread::KIND_PLATFORM_SYSTEM, $system->thread_kind, 'kind is not rewritten for system threads');
    }

    // ----------------------------------------------------------------- helpers

    private function link(MessageThread $thread): MessageThread
    {
        return app(ThreadLinker::class)->link($thread)->fresh();
    }

    private function orderItem(string $listing, ?string $sku): void
    {
        $orderId = DB::table('orders')->insertGetId(['sales_channel_id' => (string) $this->channel->id, 'buyer_username' => 'someone_else']);
        DB::table('order_items')->insert(['order_id' => $orderId, 'channel_item_id' => $listing, 'sku' => $sku]);
    }

    private function thread(
        ?string $listing,
        string $lastAt = '2026-11-18 10:00:00',
        string $externalId = 'thread',
        string $kind = MessageThread::KIND_PRE_PURCHASE,
        ?string $buyer = 'buyer_x',
    ): MessageThread {
        return MessageThread::create([
            'sales_channel_id' => $this->channel->id,
            'external_thread_id' => $externalId . ($listing ?? 'none') . $lastAt,
            'thread_kind' => $kind,
            'buyer_username' => $buyer,
            'external_reference_id' => $listing,
            'last_message_at' => $lastAt,
        ]);
    }
}
