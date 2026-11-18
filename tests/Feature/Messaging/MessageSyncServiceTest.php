<?php

namespace Tests\Feature\Messaging;

use App\Models\Message;
use App\Models\MessageChannelState;
use App\Models\MessageThread;
use App\Models\SalesChannel;
use App\Services\Messaging\Exceptions\MissingScopeException;
use App\Services\Messaging\MessageSyncService;
use App\Services\Messaging\ThreadLinker;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesMessagingTables;
use Tests\TestCase;

/**
 * Payload shapes mirror the live eBay responses captured on 2026-11-18:
 * list items carry latestMessage (buyer conversations) and no latestMessage (system ones);
 * the single-conversation call returns messages newest first and no conversationId.
 */
class MessageSyncServiceTest extends TestCase
{
    use CreatesMessagingTables;

    private SalesChannel $channel;

    /** @var array<int,array<string,mixed>> buyer conversations returned by the list call */
    private array $conversations = [];

    /** @var array<string,array<int,array<string,mixed>>> conversationId => messages (newest first) */
    private array $details = [];

    /** @var array<int,string> conversationIds whose detail call fails */
    private array $failDetail = [];

    /** When set, every list call answers with this HTTP status and an eBay error body. */
    private ?int $listStatus = null;

    private array $systemConversations = [];
    private array $systemDetails = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->createMessagingTables();

        $this->channel = SalesChannel::create([
            'name' => 'Apex Auto Body Parts',
            'type' => 'ebay',
            'client_id' => 'cid',
            'client_secret' => 'secret',
            'refresh_token' => 'refresh',
            'external_account_ids' => ['apex_auto'],
            'user_scopes' => 'https://api.ebay.com/oauth/api_scope/commerce.message',
            'access_token' => 'tok',
            'access_token_expires_at' => now()->addHour(),
        ]);

        $this->conversations = [
            $this->conversation('c1', '111', unread: 1, latest: $this->msg('m1b', 'michael_c', 'apex_auto', 'Hi, will this fit?', '2026-11-18T13:42:00.000Z', read: false)),
            $this->conversation('c2', '222', unread: 0, latest: $this->msg('m2b', 'apex_auto', 'sarah_k', 'Shipped today', '2026-11-18T12:00:00.000Z')),
            $this->conversation('c3', '333', unread: 0, latest: $this->msg('m3a', 'tom_b', 'apex_auto', 'Is this OEM?', '2026-11-18T11:00:00.000Z')),
        ];

        $this->details = [
            'c1' => [
                $this->msg('m1b', 'michael_c', 'apex_auto', 'Hi, will this fit?', '2026-11-18T13:42:00.000Z', read: false),
                $this->msg('m1a', 'apex_auto', 'michael_c', 'Your order shipped', '2026-11-17T10:00:00.000Z'),
            ],
            'c2' => [
                $this->msg('m2b', 'apex_auto', 'sarah_k', 'Shipped today', '2026-11-18T12:00:00.000Z'),
                $this->msg('m2a', 'sarah_k', 'apex_auto', 'When will it ship?', '2026-11-18T09:00:00.000Z'),
            ],
            'c3' => [
                $this->msg('m3a', 'tom_b', 'apex_auto', 'Is this OEM?', '2026-11-18T11:00:00.000Z'),
            ],
        ];

        $this->systemConversations = [[
            'conversationId' => 's1',
            'conversationStatus' => 'ACTIVE',
            'conversationTitle' => 'Return 5333331605: Issue refund',
            'conversationType' => 'FROM_EBAY',
            'createdDate' => '2026-11-18T08:24:02.000Z',
            'referenceId' => '444',
            'referenceType' => 'LISTING',
            'unreadCount' => 1,
        ]];
        $this->systemDetails = ['s1' => [$this->msg('sm1', 'eBay', 'apex_auto', 'Please refund the buyer.', '2026-11-18T08:24:02.000Z', read: false)]];

        $this->fakeEbay();
    }

    // ---------------------------------------------------------------- tests

    public function test_first_sync_imports_threads_messages_links_and_sets_status(): void
    {
        DB::table('products')->insert(['id' => 5, 'name' => 'Front Left Fender']);
        DB::table('sales_channel_product')->insert(['product_id' => 5, 'sales_channel_id' => $this->channel->id, 'external_listing_id' => '111']);
        $orderId = DB::table('orders')->insertGetId(['sales_channel_id' => (string) $this->channel->id, 'buyer_username' => 'Michael_C', 'order_date' => '2026-11-16 10:00:00']);
        DB::table('order_items')->insert(['order_id' => $orderId, 'channel_item_id' => '111']);

        $stats = $this->sync();

        $this->assertSame(3, $stats['threads_seen']);
        $this->assertSame(3, $stats['threads_created']);
        $this->assertSame(5, $stats['messages_created']);
        $this->assertSame(0, $stats['errors']);

        $c1 = $this->thread('c1');
        $this->assertSame('michael_c', $c1->buyer_username);
        $this->assertSame(MessageThread::STATUS_PENDING, $c1->status, 'buyer wrote last');
        $this->assertSame(1, $c1->unread_count);
        $this->assertSame('inbound', $c1->last_message_direction);
        $this->assertSame('Hi, will this fit?', $c1->last_message_preview);
        $this->assertSame(5, $c1->product_id);
        $this->assertSame($orderId, $c1->order_id, 'order matched on store + buyer (case-insensitive) + listing');
        $this->assertSame(MessageThread::KIND_POST_PURCHASE, $c1->thread_kind);
        $this->assertSame(2, $c1->messages()->count());

        $c2 = $this->thread('c2');
        $this->assertSame(MessageThread::STATUS_AWAITING_BUYER, $c2->status, 'we wrote last');
        $this->assertSame('outbound', $c2->last_message_direction);

        $c3 = $this->thread('c3');
        $this->assertNull($c3->order_id);
        $this->assertSame(MessageThread::KIND_PRE_PURCHASE, $c3->thread_kind);

        // eBay sends UTC; the app runs in America/Los_Angeles. The instant must survive the round trip.
        $this->assertTrue($c1->fresh()->last_message_at->equalTo(\Carbon\Carbon::parse('2026-11-18T13:42:00Z')));
        $this->assertTrue(Message::where('external_message_id', 'm1a')->first()->sent_at->equalTo(\Carbon\Carbon::parse('2026-11-17T10:00:00Z')));

        $msg = Message::where('external_message_id', 'm1a')->first();
        $this->assertSame(Message::DIRECTION_OUTBOUND, $msg->direction);
        $this->assertSame(Message::SENDER_SELLER, $msg->sender_kind);

        $state = MessageChannelState::forChannel($this->channel);
        $this->assertNotNull($state->cursor);
        $this->assertNotNull($state->last_synced_at);
        $this->assertNull($state->last_error);
    }

    public function test_second_sync_over_the_same_data_changes_nothing_and_skips_detail_calls(): void
    {
        $this->sync();
        $messagesAfterFirst = Message::count();

        $detailCallsAfterFirst = $this->detailRequestCount();
        $this->assertSame(3, $detailCallsAfterFirst);

        $stats = $this->sync();

        $this->assertSame(3, $stats['threads_seen']);
        $this->assertSame(0, $stats['threads_created']);
        $this->assertSame(0, $stats['threads_updated']);
        $this->assertSame(0, $stats['messages_created']);
        $this->assertSame(0, $stats['details_fetched']);
        $this->assertSame($messagesAfterFirst, Message::count());
        $this->assertSame(3, $this->detailRequestCount(), 'no conversation was re-fetched');
        $this->assertSame(3, MessageThread::count());
    }

    public function test_new_buyer_message_reopens_a_resolved_thread_and_our_reply_does_not(): void
    {
        $this->sync();

        $this->thread('c1')->update(['status' => MessageThread::STATUS_RESOLVED]);
        $this->thread('c2')->update(['status' => MessageThread::STATUS_RESOLVED]);

        // c1: buyer writes again. c2: we write again (e.g. from Seller Hub).
        $newBuyer = $this->msg('m1c', 'michael_c', 'apex_auto', 'Thanks, one more question', '2026-11-18T15:00:00.000Z', read: false);
        $this->details['c1'] = array_merge([$newBuyer], $this->details['c1']);
        $this->conversations[0] = $this->conversation('c1', '111', unread: 2, latest: $newBuyer);

        $newSeller = $this->msg('m2c', 'apex_auto', 'sarah_k', 'Tracking: 123', '2026-11-18T15:05:00.000Z');
        $this->details['c2'] = array_merge([$newSeller], $this->details['c2']);
        $this->conversations[1] = $this->conversation('c2', '222', unread: 0, latest: $newSeller);

        $stats = $this->sync();

        $this->assertSame(2, $stats['messages_created']);
        $this->assertSame(MessageThread::STATUS_PENDING, $this->thread('c1')->status, 'new inbound reopens');
        $this->assertSame(2, $this->thread('c1')->unread_count);
        $this->assertSame(MessageThread::STATUS_RESOLVED, $this->thread('c2')->status, 'our own reply keeps it resolved');
        $this->assertSame('Tracking: 123', $this->thread('c2')->last_message_preview);
    }

    public function test_a_pending_thread_answered_elsewhere_becomes_awaiting_buyer(): void
    {
        $this->sync();
        $this->assertSame(MessageThread::STATUS_PENDING, $this->thread('c1')->status);

        $reply = $this->msg('m1c', 'apex_auto', 'michael_c', 'Yes it fits.', '2026-11-18T14:00:00.000Z');
        $this->details['c1'] = array_merge([$reply], $this->details['c1']);
        $this->conversations[0] = $this->conversation('c1', '111', unread: 0, latest: $reply);

        $this->sync();

        $this->assertSame(MessageThread::STATUS_AWAITING_BUYER, $this->thread('c1')->status);
    }

    public function test_read_state_change_alone_updates_unread_count_without_fetching_detail(): void
    {
        $this->sync();
        $calls = $this->detailRequestCount();

        // Read on the platform: same message, unread count drops to 0.
        $this->conversations[0]['unreadCount'] = 0;

        $stats = $this->sync();

        $this->assertSame(0, $this->thread('c1')->unread_count);
        $this->assertSame(1, $stats['threads_updated']);
        $this->assertSame($calls, $this->detailRequestCount());
    }

    public function test_a_failing_thread_does_not_stop_the_others_and_holds_the_cursor_back(): void
    {
        $this->failDetail = ['c2'];

        $stats = $this->sync();

        $this->assertSame(1, $stats['errors']);
        $this->assertSame(2, MessageThread::count(), 'c1 and c3 were saved');
        $this->assertNull($this->thread('c2'));

        $state = MessageChannelState::forChannel($this->channel);
        $this->assertNull($state->cursor, 'not advanced, so c2 is retried');
        $this->assertStringContainsString('1 thread(s) failed', $state->last_error);

        $this->failDetail = [];
        $stats = $this->sync();

        $this->assertSame(0, $stats['errors']);
        $this->assertNotNull($this->thread('c2'));
        $state->refresh();
        $this->assertNotNull($state->cursor);
        $this->assertNull($state->last_error);
    }

    public function test_list_level_failure_is_recorded_and_rethrown(): void
    {
        $this->listStatus = 403;

        try {
            $this->sync();
            $this->fail('Expected MissingScopeException');
        } catch (MissingScopeException) {
            // expected
        }

        $state = MessageChannelState::forChannel($this->channel);
        $this->assertNotNull($state->last_error);
        $this->assertNull($state->cursor);
        $this->assertSame(0, MessageThread::count());
    }

    public function test_disabled_store_is_skipped_without_calling_the_platform(): void
    {
        MessageChannelState::forChannel($this->channel)->update(['messaging_enabled' => false]);

        $stats = $this->sync();

        $this->assertSame('messaging is disabled for this store', $stats['skipped']);
        Http::assertNothingSent();
    }

    public function test_system_conversations_are_skipped_unless_requested(): void
    {
        $this->sync();
        $this->assertSame(0, MessageThread::where('thread_kind', MessageThread::KIND_PLATFORM_SYSTEM)->count());
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'FROM_EBAY'));

        $stats = $this->sync(includeSystem: true);

        $system = $this->thread('s1');
        $this->assertNotNull($system);
        $this->assertSame(MessageThread::KIND_PLATFORM_SYSTEM, $system->thread_kind);
        $this->assertSame('Return 5333331605: Issue refund', $system->subject);
        $this->assertNull($system->order_id, 'system threads are never order-linked');
        $this->assertSame(Message::SENDER_PLATFORM, $system->messages()->first()->sender_kind);
        $this->assertSame(1, $stats['threads_created']);
    }

    public function test_pre_purchase_thread_gets_its_order_once_the_order_exists(): void
    {
        $this->sync();
        $this->assertSame(MessageThread::KIND_PRE_PURCHASE, $this->thread('c3')->thread_kind);

        $orderId = DB::table('orders')->insertGetId(['sales_channel_id' => (string) $this->channel->id, 'buyer_username' => 'tom_b', 'order_date' => '2026-11-18 16:00:00']);
        DB::table('order_items')->insert(['order_id' => $orderId, 'channel_item_id' => '333']);

        $linked = app(ThreadLinker::class)->relinkUnlinked($this->channel);

        $this->assertSame(1, $linked);
        $this->assertSame($orderId, $this->thread('c3')->order_id);
        $this->assertSame(MessageThread::KIND_POST_PURCHASE, $this->thread('c3')->thread_kind);
    }

    public function test_the_sync_command_reports_and_validates_options(): void
    {
        $this->artisan('messages:sync')
            ->expectsOutputToContain('3 threads seen, 3 new, 5 new messages')
            ->assertExitCode(0);

        $this->artisan('messages:sync', ['--days' => 0])
            ->expectsOutputToContain('--days must be 1 or more')
            ->assertExitCode(1);

        $this->artisan('messages:sync', ['--from' => 'not-a-date'])
            ->expectsOutputToContain('Could not read --from')
            ->assertExitCode(1);
    }

    // -------------------------------------------------------------- helpers

    private function sync(bool $includeSystem = false): array
    {
        return app(MessageSyncService::class)->syncChannel($this->channel->fresh(), null, $includeSystem);
    }

    private function thread(string $externalId): ?MessageThread
    {
        return MessageThread::where('sales_channel_id', $this->channel->id)->where('external_thread_id', $externalId)->first();
    }

    private function detailRequestCount(): int
    {
        return Http::recorded(fn (Request $r) => (bool) preg_match('#/conversation/[^/?]+#', $r->url()))->count();
    }

    /** @return array<string,mixed> */
    private function msg(string $id, string $from, string $to, string $body, string $at, bool $read = true): array
    {
        return ['messageId' => $id, 'messageBody' => $body, 'senderUsername' => $from, 'recipientUsername' => $to, 'readStatus' => $read, 'createdDate' => $at];
    }

    /** @return array<string,mixed> */
    private function conversation(string $id, string $listing, int $unread, array $latest): array
    {
        return [
            'conversationId' => $id,
            'conversationStatus' => 'ACTIVE',
            'conversationType' => 'FROM_MEMBERS',
            'createdDate' => $latest['createdDate'],
            'referenceId' => $listing,
            'referenceType' => 'LISTING',
            'unreadCount' => $unread,
            'latestMessage' => $latest,
        ];
    }

    private function fakeEbay(): void
    {
        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $q);
            $type = $q['conversation_type'] ?? 'FROM_MEMBERS';

            if (str_ends_with($path, '/conversation')) {
                if ($this->listStatus) {
                    return Http::response(['errors' => [['errorId' => 1100, 'message' => 'Insufficient permissions']]], $this->listStatus);
                }

                return Http::response(['total' => 99, 'conversations' => $type === 'FROM_EBAY' ? $this->systemConversations : $this->conversations]);
            }

            preg_match('#/conversation/([^/]+)$#', $path, $m);
            $id = urldecode($m[1] ?? '');

            if (in_array($id, $this->failDetail, true)) {
                return Http::response(['errors' => [['message' => 'bad conversation']]], 400);
            }

            $messages = $type === 'FROM_EBAY' ? ($this->systemDetails[$id] ?? []) : ($this->details[$id] ?? []);

            // Live detail responses carry no conversationId / referenceId / unreadCount.
            return Http::response([
                'total' => count($messages),
                'conversationTitle' => 'ignored',
                'conversationStatus' => 'ACTIVE',
                'conversationType' => $type,
                'messages' => $messages,
            ]);
        });
    }
}
