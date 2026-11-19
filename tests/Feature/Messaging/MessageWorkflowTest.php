<?php

namespace Tests\Feature\Messaging;

use App\Models\Message;
use App\Models\MessageSavedReply;
use App\Models\MessageThread;
use App\Models\SalesChannel;
use App\Models\User;
use App\Notifications\MessageThreadAssignedNotification;
use App\Notifications\NewBuyerMessageNotification;
use Spatie\Permission\Models\Role;
use App\Services\Messaging\MessageSyncService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesMessagingTables;
use Tests\TestCase;

/**
 * Phase 4: replying, internal notes, assignment, status, read state and saved replies.
 * The eBay Message API is always faked; nothing here reaches a real store.
 */
class MessageWorkflowTest extends TestCase
{
    use CreatesMessagingTables;

    private const SEND = 'api.ebay.com/commerce/message/v1/send_message';
    private const UPDATE = 'api.ebay.com/commerce/message/v1/update_conversation';

    private SalesChannel $apex;
    private User $agent;      // view + reply
    private User $manager;    // view + reply + assign + settings
    private User $viewer;     // view only

    protected function setUp(): void
    {
        parent::setUp();

        $this->createMessagingTables();
        $this->createAuthTables();

        $this->apex = SalesChannel::create([
            'name' => 'Store 1 (Apex Auto Body Parts)',
            'type' => 'ebay',
            'external_account_ids' => ['apex_auto'],
            'user_scopes' => 'https://api.ebay.com/oauth/api_scope/commerce.message',
            'access_token' => 'tok',
            'access_token_expires_at' => now()->addHour(),
        ]);

        $this->viewer = $this->userWith(['view messages']);
        $this->agent = $this->userWith(['view messages', 'reply messages']);
        $this->manager = $this->userWith(['view messages', 'reply messages', 'assign messages', 'manage message-settings']);
    }

    // ------------------------------------------------------------------- reply

    public function test_a_reply_is_sent_to_the_platform_and_updates_the_conversation(): void
    {
        $thread = $this->thread(unread: 2);
        $inbound = $this->message($thread, 'in-1', 'inbound', 'Is this in stock?');
        Http::fake([self::SEND => Http::response(['messageId' => 'out-1', 'conversationId' => 'c1', 'createdDate' => now()->addMinute()->utc()->toIso8601String()])]);

        $this->actingAs($this->agent)->postJson("/messages/{$thread->id}/reply", ['body' => "  Yes, it is.  "])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('thread.status', MessageThread::STATUS_AWAITING_BUYER)
            ->assertJsonPath('thread.assignee.id', $this->agent->id)
            ->assertJsonPath('messages.1.body', 'Yes, it is.')
            ->assertJsonPath('messages.1.kind', 'outbound')
            ->assertJsonPath('messages.1.sender', $this->agent->name);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/send_message')
            && $r['conversationId'] === 'c1' && $r['messageText'] === 'Yes, it is.');

        $thread->refresh();
        $this->assertSame(0, $thread->unread_count);
        $this->assertSame('outbound', $thread->last_message_direction);
        $this->assertNotNull($inbound->fresh()->read_at, 'answering a buyer reads their messages');
        $this->assertDatabaseHas('messages', ['thread_id' => $thread->id, 'external_message_id' => 'out-1', 'delivery_status' => 'sent', 'sent_by_user_id' => $this->agent->id]);
    }

    public function test_an_existing_assignee_is_kept_when_someone_else_replies(): void
    {
        $thread = $this->thread();
        $thread->update(['assigned_user_id' => $this->manager->id]);
        Http::fake([self::SEND => Http::response(['messageId' => 'out-1'])]);

        $this->actingAs($this->agent)->postJson("/messages/{$thread->id}/reply", ['body' => 'Hello'])->assertOk();

        $this->assertSame($this->manager->id, $thread->fresh()->assigned_user_id);
    }

    public function test_a_failed_send_is_kept_with_its_error_and_can_be_retried(): void
    {
        $thread = $this->thread(status: MessageThread::STATUS_PENDING);
        Http::fake([self::SEND => Http::sequence()
            ->push(['errors' => [['errorId' => 20400, 'message' => 'Invalid conversation']]], 400)
            ->push(['messageId' => 'out-1', 'createdDate' => now()->addMinute()->utc()->toIso8601String()])]);

        $res = $this->actingAs($this->agent)->postJson("/messages/{$thread->id}/reply", ['body' => 'Hello there'])
            ->assertStatus(502)
            ->assertJsonPath('success', false)
            ->assertJsonPath('messages.0.failed', true);
        $this->assertStringContainsString('not delivered', $res->json('message'));

        $failed = Message::first();
        $this->assertSame('failed', $failed->delivery_status);
        $this->assertNotEmpty($failed->error);
        $this->assertSame(MessageThread::STATUS_PENDING, $thread->fresh()->status, 'a failed send does not change the conversation');

        $this->actingAs($this->agent)->postJson("/messages/{$thread->id}/messages/{$failed->id}/retry")
            ->assertOk()
            ->assertJsonPath('messages.0.failed', false);

        $this->assertSame(1, Message::count(), 'the retry reuses the same message');
        $this->assertSame('sent', $failed->fresh()->delivery_status);
        $this->assertSame(MessageThread::STATUS_AWAITING_BUYER, $thread->fresh()->status);
    }

    public function test_a_sent_message_cannot_be_retried_and_retry_stays_inside_its_thread(): void
    {
        $thread = $this->thread();
        $other = $this->thread('c2');
        $sent = $this->message($thread, 'out-1', 'outbound', 'Done');
        Http::fake();

        $this->actingAs($this->agent)->postJson("/messages/{$thread->id}/messages/{$sent->id}/retry")->assertStatus(422);
        $this->actingAs($this->agent)->postJson("/messages/{$other->id}/messages/{$sent->id}/retry")->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_platform_notices_and_bad_text_are_rejected_without_calling_the_platform(): void
    {
        $notice = $this->thread('sys', MessageThread::STATUS_PENDING);
        $notice->update(['thread_kind' => MessageThread::KIND_PLATFORM_SYSTEM]);
        $thread = $this->thread();
        Http::fake();

        $this->actingAs($this->agent)->postJson("/messages/{$notice->id}/reply", ['body' => 'Hi'])->assertStatus(422);
        $this->actingAs($this->agent)->postJson("/messages/{$thread->id}/reply", ['body' => '   '])->assertStatus(422);
        $this->actingAs($this->agent)->postJson("/messages/{$thread->id}/reply", ['body' => str_repeat('x', 2001)])->assertStatus(422);

        Http::assertNothingSent();
        $this->assertSame(0, Message::count());
    }

    public function test_a_sent_reply_without_a_message_id_is_not_duplicated_by_the_next_sync(): void
    {
        $thread = $this->thread();
        Http::fake([
            self::SEND => Http::response(['createdDate' => now()->addMinute()->utc()->toIso8601String()]),   // no messageId
            'api.ebay.com/commerce/message/v1/conversation/*' => Http::response([
                'conversationType' => 'FROM_MEMBERS',
                'messages' => [[
                    'messageId' => 'platform-id', 'messageBody' => 'On its way', 'senderUsername' => 'apex_auto',
                    'recipientUsername' => 'michael_c', 'readStatus' => true, 'createdDate' => '2026-11-19T18:00:01.000Z',
                ]],
            ]),
        ]);

        $this->actingAs($this->agent)->postJson("/messages/{$thread->id}/reply", ['body' => 'On its way'])->assertOk();
        app(MessageSyncService::class)->refreshThread($thread->fresh());

        $this->assertSame(1, Message::count());
        $row = Message::first();
        $this->assertSame('platform-id', $row->external_message_id);
        $this->assertSame($this->agent->id, $row->sent_by_user_id, 'who on the team wrote it is kept');
    }

    // ------------------------------------------------------------------- notes

    public function test_internal_notes_stay_internal(): void
    {
        $thread = $this->thread(status: MessageThread::STATUS_PENDING, unread: 1);
        $this->message($thread, 'in-1', 'inbound', 'Hello?');
        $thread->update(['last_message_preview' => 'Hello?', 'last_message_direction' => 'inbound']);
        Http::fake();

        $this->actingAs($this->agent)->postJson("/messages/{$thread->id}/note", ['body' => 'Checked the warehouse, 2 left'])
            ->assertOk()
            ->assertJsonPath('messages.1.kind', 'note');

        Http::assertNothingSent();
        $thread->refresh();
        $this->assertSame(MessageThread::STATUS_PENDING, $thread->status);
        $this->assertSame('Hello?', $thread->last_message_preview, 'a note is not the latest message');
        $this->assertSame(1, $thread->unread_count);
        $this->assertNull(Message::where('direction', 'internal_note')->first()->external_message_id);
    }

    // ------------------------------------------------------- status and assign

    public function test_status_can_be_changed_and_only_known_values_are_accepted(): void
    {
        $thread = $this->thread(status: MessageThread::STATUS_PENDING);

        $this->actingAs($this->agent)->postJson("/messages/{$thread->id}/status", ['status' => 'resolved'])
            ->assertOk()->assertJsonPath('thread.status', 'resolved');
        $this->actingAs($this->agent)->postJson("/messages/{$thread->id}/status", ['status' => 'banana'])->assertStatus(422);

        $this->assertSame('resolved', $thread->fresh()->status);
    }

    public function test_assigning_needs_the_assign_permission_and_a_user_who_can_use_the_inbox(): void
    {
        $thread = $this->thread();
        $outsider = $this->userWith([]);
        Notification::fake();

        $this->actingAs($this->agent)->postJson("/messages/{$thread->id}/assign", ['user_id' => $this->agent->id])->assertForbidden();

        $this->actingAs($this->manager)->postJson("/messages/{$thread->id}/assign", ['user_id' => $outsider->id])->assertStatus(422);
        $this->assertNull($thread->fresh()->assigned_user_id);

        $this->actingAs($this->manager)->postJson("/messages/{$thread->id}/assign", ['user_id' => $this->agent->id])
            ->assertOk()->assertJsonPath('thread.assignee.name', $this->agent->name);
        Notification::assertSentTo($this->agent, MessageThreadAssignedNotification::class);

        $this->actingAs($this->manager)->postJson("/messages/{$thread->id}/assign", ['user_id' => null])->assertOk();
        $this->assertNull($thread->fresh()->assigned_user_id);
    }

    public function test_assigning_to_yourself_does_not_notify_you(): void
    {
        $thread = $this->thread();
        Notification::fake();

        $this->actingAs($this->manager)->postJson("/messages/{$thread->id}/assign", ['user_id' => $this->manager->id])->assertOk();

        Notification::assertNothingSent();
    }

    // -------------------------------------------------------------- read state

    public function test_mark_read_clears_unread_here_and_on_the_platform(): void
    {
        $thread = $this->thread(unread: 3);
        $m = $this->message($thread, 'in-1', 'inbound', 'Hi');
        Http::fake([self::UPDATE => Http::response([])]);

        $this->actingAs($this->agent)->postJson("/messages/{$thread->id}/read", ['read' => true])->assertOk();

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/update_conversation') && $r['conversationId'] === 'c1' && $r['read'] === true);
        $this->assertSame(0, $thread->fresh()->unread_count);
        $this->assertNotNull($m->fresh()->read_at);
    }

    public function test_mark_read_still_clears_locally_when_the_platform_refuses(): void
    {
        $thread = $this->thread(unread: 1);
        Http::fake([self::UPDATE => Http::response(['errors' => [['errorId' => 20400, 'message' => 'nope']]], 400)]);

        $this->actingAs($this->agent)->postJson("/messages/{$thread->id}/read", ['read' => true])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['warning']);

        $this->assertSame(0, $thread->fresh()->unread_count);
    }

    public function test_mark_unread_is_only_kept_when_the_platform_accepts_it(): void
    {
        $thread = $this->thread(unread: 0);
        Http::fake([self::UPDATE => Http::sequence()
            ->push(['errors' => [['errorId' => 20400, 'message' => 'nope']]], 400)
            ->push([])]);

        $this->actingAs($this->agent)->postJson("/messages/{$thread->id}/read", ['read' => false])->assertStatus(502);
        $this->assertSame(0, $thread->fresh()->unread_count, 'the next sync would undo a local-only flag, so nothing changes');

        $this->actingAs($this->agent)->postJson("/messages/{$thread->id}/read", ['read' => false])->assertOk();
        $this->assertSame(1, $thread->fresh()->unread_count);
    }

    // ------------------------------------------------------------- permissions

    public function test_a_view_only_user_can_read_but_not_act(): void
    {
        $thread = $this->thread();
        Http::fake();

        $this->actingAs($this->viewer)->getJson("/messages/{$thread->id}")
            ->assertOk()
            ->assertJsonPath('actions.can_reply', false)
            ->assertJsonPath('actions.can_note', false)
            ->assertJsonPath('actions.can_assign', false);

        foreach (['reply' => ['body' => 'x'], 'note' => ['body' => 'x'], 'status' => ['status' => 'resolved'], 'read' => ['read' => true]] as $path => $payload) {
            $this->actingAs($this->viewer)->postJson("/messages/{$thread->id}/{$path}", $payload)->assertForbidden();
        }
        $this->actingAs($this->viewer)->postJson("/messages/{$thread->id}/assign", ['user_id' => null])->assertForbidden();

        Http::assertNothingSent();
    }

    // ------------------------------------------------------------ saved replies

    public function test_saved_replies_are_offered_with_placeholders_filled_in(): void
    {
        $thread = $this->thread();
        $thread->update(['buyer_name' => 'Michael']);
        MessageSavedReply::create(['title' => 'Greeting', 'body' => 'Hi {buyer_name}, about order {order_number}: thanks!', 'is_active' => true]);
        MessageSavedReply::create(['title' => 'Hidden', 'body' => 'Not offered', 'is_active' => false]);

        $res = $this->actingAs($this->agent)->getJson("/messages/{$thread->id}")->assertOk();

        $this->assertCount(1, $res->json('actions.saved_replies'));
        $this->assertSame('Hi Michael, about order {order_number}: thanks!', $res->json('actions.saved_replies.0.body'), 'unknown values stay as written');
        $this->actingAs($this->viewer)->getJson("/messages/{$thread->id}")->assertJsonPath('actions.saved_replies', []);
    }

    public function test_saved_replies_are_managed_in_settings_with_the_settings_permission(): void
    {
        $this->actingAs($this->agent)->get('/settings/messages')->assertForbidden();
        $this->actingAs($this->agent)->post('/settings/messages/replies', ['title' => 'x', 'body' => 'y'])->assertForbidden();

        $this->actingAs($this->manager)->get('/settings/messages')->assertOk()->assertSee('Add a saved reply');

        $this->actingAs($this->manager)->post('/settings/messages/replies', ['title' => ' Shipping ', 'body' => 'Ships in 1 day', 'is_active' => '1'])
            ->assertRedirect('/settings/messages');
        $reply = MessageSavedReply::first();
        $this->assertSame('Shipping', $reply->title);
        $this->assertSame($this->manager->id, $reply->created_by);

        $this->actingAs($this->manager)->put("/settings/messages/replies/{$reply->id}", ['title' => 'Shipping', 'body' => 'Ships in 2 days', 'is_active' => '0'])->assertRedirect();
        $this->assertFalse($reply->fresh()->is_active);
        $this->assertSame('Ships in 2 days', $reply->fresh()->body);

        $this->actingAs($this->manager)->post('/settings/messages/replies', ['title' => '', 'body' => 'x'])->assertSessionHasErrors('title');

        $this->actingAs($this->manager)->delete("/settings/messages/replies/{$reply->id}")->assertRedirect();
        $this->assertSame(0, MessageSavedReply::count());
    }

    // ----------------------------------------------------------- notifications

    public function test_a_fresh_buyer_message_notifies_the_assignee_or_else_the_admins_but_history_does_not(): void
    {
        $admin = $this->userWith([]);
        Role::findOrCreate('superadmin', 'web');   // NotificationRecipients looks up both alert roles
        $admin->assignRole(Role::findOrCreate('Admin', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $current = [];
        Http::fake(["api.ebay.com/commerce/message/v1/conversation/*" => function () use (&$current) { return Http::response($current); }]);
        $reply = fn (string $id, string $at) => [
            'conversationType' => 'FROM_MEMBERS',
            'messages' => [[
                'messageId' => $id, 'messageBody' => 'Still available?', 'senderUsername' => 'michael_c',
                'recipientUsername' => 'apex_auto', 'readStatus' => false, 'createdDate' => $at,
            ]],
        ];
        $sync = fn (MessageThread $t) => app(MessageSyncService::class)->refreshThread($t->fresh());
        Notification::fake();

        // Unassigned: the admins hear about it.
        $unassigned = $this->thread('c1', last: false);
        $current = $reply('m1', now()->subMinutes(5)->utc()->toIso8601String());
        $sync($unassigned);
        Notification::assertSentTo($admin, NewBuyerMessageNotification::class);
        Notification::assertNotSentTo($this->agent, NewBuyerMessageNotification::class);

        // Assigned: only the assignee.
        $assigned = $this->thread('c2', last: false);
        $assigned->update(['assigned_user_id' => $this->agent->id]);
        $current = $reply('m2', now()->subMinutes(5)->utc()->toIso8601String());
        $sync($assigned);
        Notification::assertSentToTimes($admin, NewBuyerMessageNotification::class, 1);
        Notification::assertSentTo($this->agent, NewBuyerMessageNotification::class);

        // Old history (e.g. a backfill) is silent.
        $old = $this->thread('c3', last: false);
        $current = $reply('m3', now()->subDays(3)->utc()->toIso8601String());
        $sync($old);
        Notification::assertSentToTimes($admin, NewBuyerMessageNotification::class, 1);
    }

    // ----------------------------------------------------------------- helpers

    private function thread(string $externalId = 'c1', string $status = MessageThread::STATUS_AWAITING_BUYER, int $unread = 0, bool $last = true): MessageThread
    {
        return MessageThread::create([
            'sales_channel_id' => $this->apex->id,
            'external_thread_id' => $externalId,
            'thread_kind' => MessageThread::KIND_PRE_PURCHASE,
            'buyer_username' => 'michael_c',
            'status' => $status,
            'unread_count' => $unread,
            'last_message_at' => $last ? now()->toDateTimeString() : null,
        ]);
    }

    private function message(MessageThread $thread, string $externalId, string $direction, string $body): Message
    {
        return Message::create([
            'thread_id' => $thread->id,
            'external_message_id' => $externalId,
            'direction' => $direction,
            'sender_kind' => $direction === 'inbound' ? 'buyer' : 'seller',
            'body' => $body,
            'delivery_status' => 'sent',
            'sent_at' => now(),
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
