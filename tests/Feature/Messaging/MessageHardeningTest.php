<?php

namespace Tests\Feature\Messaging;

use App\Models\Message;
use App\Models\MessageChannelState;
use App\Models\MessageSetting;
use App\Models\MessageThread;
use App\Models\SalesChannel;
use App\Models\User;
use App\Notifications\MessageStoreNeedsReauthorizationNotification;
use App\Services\Messaging\Exceptions\MissingScopeException;
use App\Services\Messaging\MessageHealth;
use App\Services\Messaging\MessageSyncService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesMessagingTables;
use Tests\TestCase;

/**
 * Phase 6: re-authorization detection, the health view and the retention policy.
 * The eBay API is always faked.
 */
class MessageHardeningTest extends TestCase
{
    use CreatesMessagingTables;

    private const SCOPE = 'https://api.ebay.com/oauth/api_scope/commerce.message';

    protected function setUp(): void
    {
        parent::setUp();

        $this->createMessagingTables();
        $this->createAuthTables();
    }

    // ------------------------------------------------------- re-authorization

    public function test_a_store_without_the_messaging_permission_is_flagged_and_the_admins_are_told_once(): void
    {
        $admin = $this->admin();
        $store = $this->store('Store 1 (Apex Auto Body Parts)', scopes: 'https://api.ebay.com/oauth/api_scope');
        Notification::fake();

        for ($i = 0; $i < 3; $i++) {
            try {
                app(MessageSyncService::class)->syncChannel($store);
                $this->fail('the sync should refuse a store without the messaging scope');
            } catch (MissingScopeException) {
            }
        }

        $state = MessageChannelState::forChannel($store);
        $this->assertTrue($state->needs_reauthorization);
        $this->assertNotEmpty($state->last_error);
        Notification::assertSentToTimes($admin, MessageStoreNeedsReauthorizationNotification::class, 1);

        $stores = app(MessageHealth::class)->stores();
        $this->assertSame(MessageHealth::REAUTH, $stores->first()['status']);
        $this->assertSame([['id' => $store->id, 'name' => 'Apex Auto Body Parts']], app(MessageHealth::class)->needingReauthorization());
    }

    public function test_a_good_sync_after_re_authorizing_clears_the_flag(): void
    {
        $store = $this->store();
        MessageChannelState::forChannel($store)->markNeedsReauthorization('eBay refused');
        Http::fake(['api.ebay.com/commerce/message/v1/conversation*' => Http::response(['conversations' => [], 'total' => 0])]);

        app(MessageSyncService::class)->syncChannel($store);

        $state = MessageChannelState::forChannel($store)->fresh();
        $this->assertFalse($state->needs_reauthorization);
        $this->assertNull($state->last_error);
        $this->assertSame(MessageHealth::OK, app(MessageHealth::class)->stores()->first()['status']);
    }

    public function test_a_refused_reply_also_flags_the_store(): void
    {
        $store = $this->store();
        $thread = MessageThread::create([
            'sales_channel_id' => $store->id, 'external_thread_id' => 'c1', 'thread_kind' => MessageThread::KIND_PRE_PURCHASE,
            'status' => MessageThread::STATUS_PENDING,
        ]);
        Http::fake(['api.ebay.com/commerce/message/v1/send_message' => Http::response(['errors' => [['errorId' => 1100, 'message' => 'Insufficient permissions']]], 403)]);
        Permission::findOrCreate('view messages', 'web');
        Permission::findOrCreate('reply messages', 'web');
        $user = User::forceCreate(['name' => 'Agent']);
        $user->givePermissionTo(['view messages', 'reply messages']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user)->postJson("/messages/{$thread->id}/reply", ['body' => 'Hello'])->assertStatus(502);

        $this->assertTrue(MessageChannelState::forChannel($store)->needs_reauthorization);
    }

    public function test_the_inbox_shows_a_banner_while_a_store_needs_re_authorizing(): void
    {
        $store = $this->store('Store 1 (Apex Auto Body Parts)');
        $user = $this->viewer();

        $this->actingAs($user)->get('/messages')->assertOk()->assertDontSee('stopped syncing messages');

        MessageChannelState::forChannel($store)->markNeedsReauthorization('eBay refused');
        $this->actingAs($user)->get('/messages')->assertOk()
            ->assertSee('stopped syncing messages')
            ->assertSee('Apex Auto Body Parts')
            ->assertSee('Ask an administrator');   // this user cannot edit stores
    }

    // ---------------------------------------------------------- health states

    public function test_health_tells_syncing_behind_failing_and_new_stores_apart(): void
    {
        $ok = $this->store('Ok');
        $stale = $this->store('Stale');
        $failing = $this->store('Failing');
        $new = $this->store('New');
        $off = $this->store('Off');

        MessageChannelState::forChannel($ok)->update(['last_synced_at' => now()->subMinutes(2)]);
        MessageChannelState::forChannel($stale)->update(['last_synced_at' => now()->subHour()]);
        MessageChannelState::forChannel($failing)->update(['last_synced_at' => now()->subMinutes(2), 'last_error' => 'eBay 500', 'last_error_at' => now()]);
        MessageChannelState::forChannel($off)->update(['messaging_enabled' => false]);

        $byName = app(MessageHealth::class)->stores()->keyBy('name');

        $this->assertSame(MessageHealth::OK, $byName['Ok']['status']);
        $this->assertSame(MessageHealth::STALE, $byName['Stale']['status']);
        $this->assertSame(MessageHealth::FAILING, $byName['Failing']['status']);
        $this->assertSame(MessageHealth::NEVER, $byName['New']['status']);
        $this->assertArrayNotHasKey('Off', $byName->all(), 'a store switched off on purpose is not a problem');

        $summary = app(MessageHealth::class)->summary();
        $this->assertSame(4, $summary['total']);
        $this->assertSame(1, $summary['healthy']);

        $html = view('system-health._messages', [
            'messageStores' => app(MessageHealth::class)->stores(),
            'messageWorkload' => app(MessageHealth::class)->workload(),
        ])->render();
        $this->assertStringContainsString('Behind', $html);
        $this->assertStringContainsString('eBay 500', $html);
    }

    // --------------------------------------------------------------- retention

    public function test_prune_deletes_only_old_resolved_conversations_with_their_messages(): void
    {
        $store = $this->store();
        MessageSetting::create(['timezone' => 'UTC', 'office_hours' => [], 'auto_reply_cooldown_hours' => 12, 'retention_days' => 90]);

        $oldResolved = $this->thread($store, 'old-resolved', MessageThread::STATUS_RESOLVED, now()->subDays(120));
        $recentResolved = $this->thread($store, 'recent-resolved', MessageThread::STATUS_RESOLVED, now()->subDays(10));
        $oldPending = $this->thread($store, 'old-pending', MessageThread::STATUS_PENDING, now()->subDays(400));
        $oldAwaiting = $this->thread($store, 'old-awaiting', MessageThread::STATUS_AWAITING_BUYER, now()->subDays(400));

        $this->artisan('messages:prune --dry-run')->assertSuccessful();
        $this->assertSame(4, MessageThread::count(), 'a dry run changes nothing');

        $this->artisan('messages:prune')->assertSuccessful();

        $this->assertNull(MessageThread::find($oldResolved->id));
        $this->assertSame(0, Message::where('thread_id', $oldResolved->id)->count());
        $this->assertNotNull(MessageThread::find($recentResolved->id));
        $this->assertNotNull(MessageThread::find($oldPending->id), 'open conversations are never deleted');
        $this->assertNotNull(MessageThread::find($oldAwaiting->id));
        $this->assertSame(1, Message::where('thread_id', $recentResolved->id)->count());
    }

    public function test_prune_keeps_everything_when_no_period_is_set_and_refuses_a_tiny_one(): void
    {
        $store = $this->store();
        $old = $this->thread($store, 'old', MessageThread::STATUS_RESOLVED, now()->subDays(900));

        $this->artisan('messages:prune')->assertSuccessful();
        $this->assertNotNull(MessageThread::find($old->id), 'blank = keep forever');

        $this->artisan('messages:prune --days=3')->assertFailed();
        $this->assertNotNull(MessageThread::find($old->id));

        $this->artisan('messages:prune --days=30')->assertSuccessful();
        $this->assertNull(MessageThread::find($old->id));
    }

    public function test_prune_clears_old_raw_payloads_but_keeps_the_conversation_readable(): void
    {
        $store = $this->store();
        $thread = $this->thread($store, 't', MessageThread::STATUS_PENDING, now()->subDays(100));
        $recent = $this->thread($store, 't2', MessageThread::STATUS_PENDING, now());
        Message::where('thread_id', $thread->id)->update(['raw' => json_encode(['x' => 1]), 'created_at' => now()->subDays(100)]);
        Message::where('thread_id', $recent->id)->update(['raw' => json_encode(['x' => 2])]);

        $this->artisan('messages:prune')->assertSuccessful();

        $old = Message::where('thread_id', $thread->id)->first();
        $this->assertNull($old->raw);
        $this->assertSame('hello', $old->body);
        $this->assertNotNull(Message::where('thread_id', $recent->id)->first()->raw);
    }

    public function test_the_retention_period_is_set_from_settings(): void
    {
        Permission::findOrCreate('manage message-settings', 'web');
        $user = User::forceCreate(['name' => 'Manager']);
        $user->givePermissionTo('manage message-settings');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $hours = [];
        foreach (MessageSetting::DAYS as $d) {
            $hours[$d] = ['open' => '09:00', 'close' => '17:00'];
        }
        $payload = ['auto_reply_enabled' => '0', 'timezone' => 'UTC', 'auto_reply_text' => '', 'auto_reply_cooldown_hours' => 12, 'hours' => $hours];

        $this->actingAs($user)->put('/settings/messages/auto-reply', $payload + ['retention_days' => 365])->assertRedirect();
        $this->assertSame(365, MessageSetting::current()->retention_days);

        $this->actingAs($user)->put('/settings/messages/auto-reply', $payload + ['retention_days' => 7])->assertSessionHasErrors('retention_days');
        $this->assertSame(365, MessageSetting::current()->retention_days);

        $this->actingAs($user)->put('/settings/messages/auto-reply', $payload + ['retention_days' => ''])->assertRedirect();
        $this->assertNull(MessageSetting::current()->retention_days, 'blank turns retention off');
    }

    // ----------------------------------------------------------------- helpers

    private function store(string $name = 'Store 1', string $scopes = self::SCOPE): SalesChannel
    {
        return SalesChannel::create([
            'name' => $name,
            'type' => 'ebay',
            'external_account_ids' => ['apex_auto'],
            'user_scopes' => $scopes,
            'access_token' => 'tok',
            'access_token_expires_at' => now()->addHour(),
        ]);
    }

    private function thread(SalesChannel $store, string $externalId, string $status, \Carbon\CarbonInterface $lastAt): MessageThread
    {
        $thread = MessageThread::create([
            'sales_channel_id' => $store->id,
            'external_thread_id' => $externalId,
            'thread_kind' => MessageThread::KIND_POST_PURCHASE,
            'status' => $status,
            'last_message_at' => $lastAt->toDateTimeString(),
        ]);

        Message::create([
            'thread_id' => $thread->id, 'external_message_id' => $externalId . '-1', 'direction' => 'inbound', 'sender_kind' => 'buyer',
            'body' => 'hello', 'delivery_status' => 'sent', 'sent_at' => $lastAt->toDateTimeString(),
        ]);

        return $thread;
    }

    private function viewer(): User
    {
        Permission::findOrCreate('view messages', 'web');
        $user = User::forceCreate(['name' => 'Viewer']);
        $user->givePermissionTo('view messages');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function admin(): User
    {
        $admin = User::forceCreate(['name' => 'Admin']);
        Role::findOrCreate('superadmin', 'web');   // NotificationRecipients looks up both alert roles
        $admin->assignRole(Role::findOrCreate('Admin', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $admin;
    }
}
