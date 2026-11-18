<?php

namespace Tests\Feature\Messaging;

use App\Models\Message;
use App\Models\MessageChannelState;
use App\Models\MessageThread;
use App\Models\SalesChannel;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesMessagingTables;
use Tests\TestCase;

class MessageInboxTest extends TestCase
{
    use CreatesMessagingTables;

    private SalesChannel $apex;
    private SalesChannel $satmec;
    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createMessagingTables();
        $this->createAuthTables();

        $this->apex = $this->channel('Store 1 (Apex Auto Body Parts)');
        $this->satmec = $this->channel('Store 2 (Satmec)');

        $this->viewer = $this->userWith(['view messages']);
    }

    // ------------------------------------------------------------ access control

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/messages/feed')->assertRedirect();
        $this->get('/messages')->assertRedirect();
    }

    public function test_users_without_the_permission_are_forbidden_everywhere(): void
    {
        $thread = $this->thread($this->apex, 'c1', 'michael_c');
        $nobody = $this->userWith([]);

        $this->actingAs($nobody)->get('/messages')->assertForbidden();
        $this->actingAs($nobody)->getJson('/messages/feed')->assertForbidden();
        $this->actingAs($nobody)->getJson('/messages/counts')->assertForbidden();
        $this->actingAs($nobody)->getJson("/messages/{$thread->id}")->assertForbidden();
        $this->actingAs($nobody)->postJson("/messages/{$thread->id}/refresh")->assertForbidden();
    }

    // -------------------------------------------------------------------- feed

    public function test_feed_lists_threads_newest_first_with_display_fields(): void
    {
        $old = $this->thread($this->apex, 'old', 'old_buyer', lastAt: '2026-11-10 10:00:00', preview: 'Older message');
        $new = $this->thread($this->satmec, 'new', 'toagu_66', lastAt: '2026-11-18 10:00:00', preview: 'Newest message', unread: 2, status: MessageThread::STATUS_PENDING);

        $res = $this->actingAs($this->viewer)->getJson('/messages/feed')->assertOk();

        $res->assertJsonPath('threads.0.id', $new->id)
            ->assertJsonPath('threads.1.id', $old->id)
            ->assertJsonPath('threads.0.buyer', 'toagu_66')
            ->assertJsonPath('threads.0.initials', 'TO')
            ->assertJsonPath('threads.0.unread', 2)
            ->assertJsonPath('threads.0.status_label', 'Needs reply')
            ->assertJsonPath('threads.0.store.short', 'Satmec')
            ->assertJsonPath('has_more', false);
    }

    public function test_tabs_store_filter_and_counts(): void
    {
        $this->thread($this->apex, 'a', 'b1', status: MessageThread::STATUS_PENDING, unread: 1);
        $this->thread($this->apex, 'b', 'b2', status: MessageThread::STATUS_AWAITING_BUYER);
        $this->thread($this->apex, 'c', 'b3', status: MessageThread::STATUS_AWAITING_BUYER, assignee: $this->viewer);
        $this->thread($this->satmec, 'd', 'b4', status: MessageThread::STATUS_RESOLVED);

        $feed = fn (string $qs) => $this->actingAs($this->viewer)->getJson('/messages/feed?' . $qs)->assertOk();

        $this->assertCount(4, $feed('tab=all')->json('threads'));
        $this->assertCount(1, $feed('tab=unread')->json('threads'));
        $this->assertCount(1, $feed('tab=pending')->json('threads'));
        $this->assertCount(2, $feed('tab=awaiting')->json('threads'));
        $this->assertCount(1, $feed('tab=resolved')->json('threads'));
        $this->assertCount(1, $feed('tab=mine')->json('threads'), 'assigned to the signed-in user');
        $this->assertCount(3, $feed('tab=all&store=' . $this->apex->id)->json('threads'));

        $this->assertSame(
            ['all' => 4, 'unread' => 1, 'pending' => 1, 'mine' => 1, 'awaiting' => 2, 'resolved' => 1],
            $feed('tab=all')->json('counts'),
        );
        $this->assertSame(3, $feed('tab=all&store=' . $this->apex->id)->json('counts.all'), 'counts follow the store filter');
        $this->assertSame(1, $feed('tab=awaiting&store=' . $this->satmec->id)->json('counts.resolved'), 'counts ignore the tab');
    }

    public function test_search_matches_buyer_preview_message_text_and_order_number(): void
    {
        $byBuyer = $this->thread($this->apex, 's1', 'fender_fan');
        $byPreview = $this->thread($this->apex, 's2', 'x2', preview: 'Does the bumper include fog lights?');
        $byBody = $this->thread($this->apex, 's3', 'x3', preview: 'ok');
        Message::create(['thread_id' => $byBody->id, 'external_message_id' => 'm', 'direction' => 'inbound', 'sender_kind' => 'buyer', 'body' => 'Need the VIN check for my Civic', 'sent_at' => now()]);
        $byOrder = $this->thread($this->apex, 's4', 'x4');
        $orderId = DB::table('orders')->insertGetId(['sales_channel_id' => (string) $this->apex->id, 'buyer_username' => 'x4', 'order_date' => now()]);
        DB::table('orders')->where('id', $orderId)->update(['order_date' => now()]);
        $this->thread($this->apex, 's5', 'unrelated');

        $search = fn (string $q) => collect($this->actingAs($this->viewer)->getJson('/messages/feed?q=' . urlencode($q))->assertOk()->json('threads'))->pluck('id')->all();

        $this->assertSame([$byBuyer->id], $search('fender'));
        $this->assertSame([$byPreview->id], $search('fog lights'));
        $this->assertSame([$byBody->id], $search('VIN check'));
        $this->assertSame([], $search('no such thing'));
        $this->assertSame([], $search('%'), 'wildcards are escaped, not treated as match-everything');
    }

    public function test_feed_paginates_in_pages_of_thirty(): void
    {
        for ($i = 1; $i <= 35; $i++) {
            $this->thread($this->apex, "t{$i}", "buyer{$i}", lastAt: now()->subMinutes($i)->toDateTimeString());
        }

        $p1 = $this->actingAs($this->viewer)->getJson('/messages/feed?page=1')->json();
        $p2 = $this->actingAs($this->viewer)->getJson('/messages/feed?page=2')->json();

        $this->assertCount(30, $p1['threads']);
        $this->assertTrue($p1['has_more']);
        $this->assertCount(5, $p2['threads']);
        $this->assertFalse($p2['has_more']);
        $this->assertSame([], array_intersect(array_column($p1['threads'], 'id'), array_column($p2['threads'], 'id')));
    }

    public function test_feed_rejects_bad_parameters(): void
    {
        $this->actingAs($this->viewer)->getJson('/messages/feed?tab=bogus')->assertStatus(422);
        $this->actingAs($this->viewer)->getJson('/messages/feed?page=0')->assertStatus(422);
    }

    public function test_counts_endpoint_returns_sidebar_numbers(): void
    {
        $this->thread($this->apex, 'a', 'b1', status: MessageThread::STATUS_PENDING, unread: 3);
        $this->thread($this->apex, 'b', 'b2', status: MessageThread::STATUS_AWAITING_BUYER);

        $this->actingAs($this->viewer)->getJson('/messages/counts')
            ->assertOk()
            ->assertExactJson(['unread' => 1, 'pending' => 1]);
    }

    // -------------------------------------------------------------------- show

    public function test_show_returns_messages_in_order_with_product_and_order_blocks(): void
    {
        DB::table('products')->insert(['id' => 5, 'name' => 'Front Left Fender']);
        DB::table('sales_channel_product')->insert(['product_id' => 5, 'sales_channel_id' => $this->apex->id, 'external_listing_id' => '111']);
        $orderId = DB::table('orders')->insertGetId(['sales_channel_id' => (string) $this->apex->id, 'buyer_username' => 'michael_c', 'order_date' => now()]);

        $thread = $this->thread($this->apex, 'c1', 'michael_c', status: MessageThread::STATUS_PENDING);
        $thread->update(['external_reference_id' => '111', 'reference_type' => 'LISTING', 'product_id' => 5, 'order_id' => $orderId, 'thread_kind' => MessageThread::KIND_POST_PURCHASE]);

        Message::create(['thread_id' => $thread->id, 'external_message_id' => 'm2', 'direction' => 'outbound', 'sender_kind' => 'seller', 'sender_name' => 'apex_auto', 'body' => 'Please share your VIN', 'sent_at' => '2026-11-18 10:05:00']);
        Message::create(['thread_id' => $thread->id, 'external_message_id' => 'm1', 'direction' => 'inbound', 'sender_kind' => 'buyer', 'sender_name' => 'michael_c', 'body' => 'Will it fit?', 'sent_at' => '2026-11-18 10:00:00', 'attachments' => [['name' => 'p.jpg', 'type' => 'IMAGE', 'url' => 'https://i.ebayimg.com/p.jpg']]]);

        $res = $this->actingAs($this->viewer)->getJson("/messages/{$thread->id}")->assertOk();

        $res->assertJsonPath('thread.buyer', 'michael_c')
            ->assertJsonPath('thread.kind_label', 'After purchase')
            ->assertJsonPath('thread.store.short', 'Apex Auto Body Parts')
            ->assertJsonPath('thread.assignee', null)
            ->assertJsonPath('messages.0.body', 'Will it fit?')
            ->assertJsonPath('messages.0.kind', 'inbound')
            ->assertJsonPath('messages.0.attachments.0.type', 'IMAGE')
            ->assertJsonPath('messages.1.body', 'Please share your VIN')
            ->assertJsonPath('messages.1.kind', 'outbound')
            ->assertJsonPath('product.name', 'Front Left Fender')
            ->assertJsonPath('product.linked', true)
            ->assertJsonPath('order.url', null);   // this user cannot view orders
    }

    public function test_order_link_is_only_given_to_users_who_can_view_orders(): void
    {
        $orderId = DB::table('orders')->insertGetId(['sales_channel_id' => (string) $this->apex->id, 'buyer_username' => 'michael_c', 'order_date' => now()]);
        $thread = $this->thread($this->apex, 'c1', 'michael_c');
        $thread->update(['order_id' => $orderId]);

        $withOrders = $this->userWith(['view messages', 'view orders']);

        $url = $this->actingAs($withOrders)->getJson("/messages/{$thread->id}")->json('order.url');

        $this->assertSame(url("/orders/{$orderId}"), $url);
    }

    public function test_show_for_a_thread_without_reference_has_no_product_or_order(): void
    {
        $thread = $this->thread($this->apex, 'c1', 'someone');

        $this->actingAs($this->viewer)->getJson("/messages/{$thread->id}")
            ->assertOk()
            ->assertJsonPath('product', null)
            ->assertJsonPath('order', null);

        $this->actingAs($this->viewer)->getJson('/messages/999999')->assertNotFound();
    }

    // ----------------------------------------------------------------- refresh

    public function test_refresh_pulls_newer_messages_and_returns_the_updated_conversation(): void
    {
        $thread = $this->thread($this->apex, 'c1', 'michael_c', status: MessageThread::STATUS_AWAITING_BUYER);
        $thread->update(['last_message_at' => '2026-11-18 09:00:00', 'external_updated_at' => '2026-11-18 09:00:00']);

        Http::fake(['api.ebay.com/commerce/message/v1/conversation/*' => Http::response([
            'total' => 1,
            'conversationType' => 'FROM_MEMBERS',
            'messages' => [[
                'messageId' => 'new-1', 'messageBody' => 'Here is my VIN', 'senderUsername' => 'michael_c',
                'recipientUsername' => 'apex_auto', 'readStatus' => false, 'createdDate' => '2026-11-18T20:00:00.000Z',
            ]],
        ])]);

        $res = $this->actingAs($this->viewer)->postJson("/messages/{$thread->id}/refresh")->assertOk();

        $res->assertJsonPath('success', true)
            ->assertJsonPath('new_messages', 1)
            ->assertJsonPath('messages.0.body', 'Here is my VIN')
            ->assertJsonPath('thread.status', MessageThread::STATUS_PENDING);

        Http::assertSent(fn (Request $r) => $r->method() === 'GET');   // a read-only call
        Http::assertNotSent(fn (Request $r) => $r->method() !== 'GET');
    }

    public function test_refresh_failure_returns_the_stored_copy_with_an_error(): void
    {
        $thread = $this->thread($this->apex, 'c1', 'michael_c');
        Message::create(['thread_id' => $thread->id, 'external_message_id' => 'm1', 'direction' => 'inbound', 'sender_kind' => 'buyer', 'body' => 'Stored text', 'sent_at' => now()]);

        Http::fake(['api.ebay.com/*' => Http::response(['errors' => [['errorId' => 1100, 'message' => 'Insufficient permissions']]], 403)]);

        $this->actingAs($this->viewer)->postJson("/messages/{$thread->id}/refresh")
            ->assertStatus(502)
            ->assertJsonPath('success', false)
            ->assertJsonPath('messages.0.body', 'Stored text');
    }

    // -------------------------------------------------------------------- page

    public function test_the_page_renders_with_stores_and_sync_health(): void
    {
        MessageChannelState::forChannel($this->apex)->markSynced();
        MessageChannelState::forChannel($this->satmec)->markFailed('eBay rejected the request: Insufficient permissions');

        $this->actingAs($this->viewer)->get('/messages')
            ->assertOk()
            ->assertSee('Select a conversation')
            ->assertSee('Apex Auto Body Parts')
            ->assertSee('1 of 2 stores need attention', false)
            ->assertSee('Satmec: eBay rejected the request', false);
    }

    // ----------------------------------------------------------------- helpers

    private function channel(string $name): SalesChannel
    {
        return SalesChannel::create([
            'name' => $name,
            'type' => 'ebay',
            'external_account_ids' => ['apex_auto'],
            'user_scopes' => 'https://api.ebay.com/oauth/api_scope/commerce.message',
            'access_token' => 'tok',
            'access_token_expires_at' => now()->addHour(),
        ]);
    }

    private function thread(
        SalesChannel $channel,
        string $externalId,
        string $buyer,
        ?string $lastAt = null,
        string $preview = 'hello',
        int $unread = 0,
        string $status = MessageThread::STATUS_AWAITING_BUYER,
        ?User $assignee = null,
    ): MessageThread {
        return MessageThread::create([
            'sales_channel_id' => $channel->id,
            'external_thread_id' => $externalId,
            'thread_kind' => MessageThread::KIND_PRE_PURCHASE,
            'buyer_username' => $buyer,
            'status' => $status,
            'unread_count' => $unread,
            'last_message_at' => $lastAt ?? now()->toDateTimeString(),
            'last_message_preview' => $preview,
            'last_message_direction' => 'inbound',
            'assigned_user_id' => $assignee?->id,
        ]);
    }

    /**
     * @param array<int,string> $permissions
     */
    private function userWith(array $permissions): User
    {
        $user = User::forceCreate(['name' => 'Tester ' . (User::count() + 1)]);

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
            $user->givePermissionTo($name);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }
}
