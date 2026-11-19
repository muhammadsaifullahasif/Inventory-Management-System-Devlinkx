<?php

namespace Tests\Feature\Messaging;

use App\Models\Message;
use App\Models\MessageChannelState;
use App\Models\MessageSetting;
use App\Models\MessageThread;
use App\Models\SalesChannel;
use App\Models\User;
use App\Services\Messaging\MessageSyncService;
use App\Services\Messaging\OfficeHours;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesMessagingTables;
use Tests\TestCase;

/**
 * Auto-reply + office hours. The eBay Message API is always faked; nothing reaches a real store.
 */
class AutoReplyTest extends TestCase
{
    use CreatesMessagingTables;

    private const SEND = 'api.ebay.com/commerce/message/v1/send_message';
    private const AUTO_TEXT = 'We are offline, hi {buyer_name}.';

    private SalesChannel $apex;
    /** @var array<string,mixed> what the faked conversation endpoint returns */
    private array $conversation = [];
    private int $sends = 0;
    private int $sendStatus = 200;
    private bool $sendReturnsId = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createMessagingTables();
        $this->createAuthTables();

        $this->apex = SalesChannel::create([
            'name' => 'Store 1',
            'type' => 'ebay',
            'external_account_ids' => ['apex_auto'],
            'user_scopes' => 'https://api.ebay.com/oauth/api_scope/commerce.message',
            'access_token' => 'tok',
            'access_token_expires_at' => now()->addHour(),
        ]);

        // Team is offline all week, auto-reply on globally and for the store.
        $this->settings(closedAllWeek: true);
        MessageChannelState::forChannel($this->apex)->update(['auto_reply_enabled' => true]);

        Http::fake([
            'api.ebay.com/commerce/message/v1/conversation/*' => fn () => Http::response($this->conversation),
            self::SEND => function () {
                $this->sends++;

                return $this->sendStatus === 200
                    ? Http::response(($this->sendReturnsId ? ['messageId' => 'auto-' . $this->sends] : []) + ['createdDate' => now()->utc()->toIso8601String()])
                    : Http::response(['errors' => [['errorId' => 1, 'message' => 'nope']]], $this->sendStatus);
            },
        ]);
    }

    // ------------------------------------------------------------- office hours

    public function test_office_hours_follow_the_weekday_and_the_time_zone(): void
    {
        $hours = new OfficeHours(new MessageSetting([
            'timezone' => 'America/New_York',
            'office_hours' => ['mon' => ['open' => '09:00', 'close' => '17:00'], 'tue' => null],
        ]));

        // Monday 2026-11-16, 14:00 UTC = 09:00 New York (opening time is inclusive)
        $this->assertTrue($hours->isOpen(\Carbon\Carbon::parse('2026-11-16 14:00:00', 'UTC')));
        $this->assertFalse($hours->isOpen(\Carbon\Carbon::parse('2026-11-16 13:59:00', 'UTC')));
        // closing time is exclusive: 22:00 UTC = 17:00 New York
        $this->assertFalse($hours->isOpen(\Carbon\Carbon::parse('2026-11-16 22:00:00', 'UTC')));
        // Tuesday is closed all day; Wednesday is not listed at all
        $this->assertFalse($hours->isOpen(\Carbon\Carbon::parse('2026-11-17 16:00:00', 'UTC')));
        $this->assertFalse($hours->isOpen(\Carbon\Carbon::parse('2026-11-18 16:00:00', 'UTC')));
    }

    public function test_unreadable_hours_count_as_open_so_nothing_is_sent_by_mistake(): void
    {
        $hours = new OfficeHours(new MessageSetting(['timezone' => 'UTC', 'office_hours' => ['mon' => ['open' => 'soon', 'close' => '17:00']]]));

        $this->assertTrue($hours->isOpen(\Carbon\Carbon::parse('2026-11-16 03:00:00', 'UTC')));
    }

    // -------------------------------------------------------------- the reply

    public function test_a_buyer_writing_after_hours_gets_one_automatic_reply(): void
    {
        $thread = $this->thread();
        $this->buyerWrites('m1');

        $this->sync($thread);

        $this->assertSame(1, $this->sends);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/send_message')
            && $r['conversationId'] === 'c1' && $r['messageText'] === 'We are offline, hi michael_c.');

        $auto = Message::where('is_auto_reply', true)->first();
        $this->assertNotNull($auto);
        $this->assertSame('outbound', $auto->direction);
        $this->assertSame('auto_reply', $auto->sender_kind);
        $this->assertSame('auto-1', $auto->external_message_id);

        $thread->refresh();
        $this->assertNotNull($thread->auto_replied_at);
        // A person is still needed: status, preview and unread are those of the buyer's message.
        $this->assertSame(MessageThread::STATUS_PENDING, $thread->status);
        $this->assertSame('inbound', $thread->last_message_direction);
        $this->assertSame('Is this in stock?', $thread->last_message_preview);
        $this->assertNull(Message::where('direction', 'inbound')->first()->read_at, 'the buyer message stays unread');
    }

    public function test_the_same_message_is_not_answered_twice_and_the_cooldown_holds(): void
    {
        $thread = $this->thread();
        $this->buyerWrites('m1');
        $this->sync($thread);
        $this->sync($thread);                       // same data again
        $this->assertSame(1, $this->sends);

        $this->buyerWrites('m1', 'm2');             // buyer writes again, 2 minutes later, inside the cooldown
        $this->sync($thread);
        $this->assertSame(1, $this->sends, 'one automatic reply per cooldown window');

        $thread->refresh()->update(['auto_replied_at' => now()->subHours(13)]);
        $this->buyerWrites('m1', 'm2', 'm3');
        $this->sync($thread);
        $this->assertSame(2, $this->sends, 'allowed again once the cooldown has passed');
    }

    public function test_the_platform_copy_of_the_automatic_reply_is_not_added_again(): void
    {
        $this->sendReturnsId = false;   // eBay did not tell us the id of what we sent
        $thread = $this->thread();
        $this->buyerWrites('m1');
        $this->sync($thread);

        // eBay now lists our automatic message too, under a different id than the send response gave.
        $this->conversation['messages'][] = $this->raw('platform-id', 'apex_auto', 'michael_c', 'We are offline, hi michael_c.', now()->addSecond());
        $this->conversation['messages'][0]['messageId'] = 'm1';
        $this->sync($thread);

        $this->assertSame(2, Message::count(), 'buyer message + one automatic reply');
        $this->assertSame(MessageThread::STATUS_PENDING, $thread->fresh()->status, 'still waiting for a person');
        $this->assertSame(1, $this->sends);
    }

    // ------------------------------------------------------------------ guards

    public function test_nothing_is_sent_while_the_team_is_online(): void
    {
        $this->settings(closedAllWeek: false);
        $this->buyerWrites('m1');

        $this->sync($this->thread());

        $this->assertSame(0, $this->sends);
    }

    public function test_it_needs_both_the_global_and_the_store_switch(): void
    {
        $thread = $this->thread();
        $this->buyerWrites('m1');

        $this->settings(closedAllWeek: true, enabled: false);
        $this->sync($thread);
        $this->assertSame(0, $this->sends, 'global switch off');

        $this->settings(closedAllWeek: true, enabled: true);
        MessageChannelState::forChannel($this->apex)->update(['auto_reply_enabled' => false]);
        $this->sync($thread);
        $this->assertSame(0, $this->sends, 'store switch off');

        MessageChannelState::forChannel($this->apex)->update(['auto_reply_enabled' => true]);
        $this->settings(closedAllWeek: true, text: '   ');
        $this->sync($thread);
        $this->assertSame(0, $this->sends, 'nothing to send');
    }

    public function test_platform_notices_and_unclassified_threads_are_never_answered(): void
    {
        foreach ([MessageThread::KIND_PLATFORM_SYSTEM, MessageThread::KIND_UNKNOWN] as $i => $kind) {
            $thread = $this->thread("sys{$i}", $kind);
            $this->buyerWrites("s{$i}");
            $this->sync($thread);
        }

        $this->assertSame(0, $this->sends);
    }

    public function test_it_never_answers_the_seller_or_a_conversation_the_team_already_answered(): void
    {
        $thread = $this->thread();

        // The buyer wrote, then we (the seller) answered on eBay: latest message is ours.
        $this->conversation = [
            'conversationType' => 'FROM_MEMBERS',
            'messages' => [
                $this->raw('m1', 'michael_c', 'apex_auto', 'Is this in stock?', now()->subMinutes(10)),
                $this->raw('m2', 'apex_auto', 'michael_c', 'Yes it is', now()->subMinutes(5)),
            ],
        ];
        $this->sync($thread);

        $this->assertSame(0, $this->sends);
    }

    public function test_old_messages_are_history_and_not_answered(): void
    {
        $thread = $this->thread();
        $this->buyerWritesAt(now()->subHours(3), 'm1');

        $this->sync($thread);

        $this->assertSame(0, $this->sends);
    }

    public function test_a_failed_send_never_breaks_the_sync_and_does_not_use_up_the_cooldown(): void
    {
        $thread = $this->thread();
        $this->buyerWrites('m1');
        $this->sendStatus = 500;

        $this->sync($thread);   // must not throw

        $this->assertSame(1, Message::count(), 'only the buyer message is stored');
        $this->assertNull($thread->fresh()->auto_replied_at);
        $this->assertSame(MessageThread::STATUS_PENDING, $thread->fresh()->status);
    }

    // ---------------------------------------------------------------- settings

    public function test_office_hours_and_the_per_store_switch_are_saved_from_settings(): void
    {
        Permission::findOrCreate('manage message-settings', 'web');
        $user = User::forceCreate(['name' => 'Manager']);
        $user->givePermissionTo('manage message-settings');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $other = User::forceCreate(['name' => 'Nobody']);

        $hours = [];
        foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $d) {
            $hours[$d] = $d === 'sun' ? ['open' => '08:00', 'close' => '17:00', 'closed' => '1'] : ['open' => '09:00', 'close' => '18:00'];
        }
        $payload = [
            'auto_reply_enabled' => '1', 'timezone' => 'Europe/London', 'auto_reply_text' => 'Back soon',
            'auto_reply_cooldown_hours' => 6, 'hours' => $hours, 'stores' => [$this->apex->id],
        ];

        $this->actingAs($user)->put('/settings/messages/auto-reply', $payload)->assertRedirect();

        $s = MessageSetting::current();
        $this->assertTrue($s->auto_reply_enabled);
        $this->assertSame('Europe/London', $s->timezone);
        $this->assertSame(['open' => '09:00', 'close' => '18:00'], $s->office_hours['mon']);
        $this->assertNull($s->office_hours['sun']);
        $this->assertTrue(MessageChannelState::forChannel($this->apex)->auto_reply_enabled);

        // A closing time before the opening time is refused, and turning a store off is saved.
        $bad = $payload;
        $bad['hours']['mon'] = ['open' => '18:00', 'close' => '09:00'];
        $bad['stores'] = [];
        $this->actingAs($user)->put('/settings/messages/auto-reply', $bad)->assertSessionHasErrors('hours.mon');
        $this->assertSame('09:00', MessageSetting::current()->office_hours['mon']['open'], 'nothing saved on error');

        $payload['stores'] = [];
        $this->actingAs($user)->put('/settings/messages/auto-reply', $payload)->assertRedirect();
        $this->assertFalse(MessageChannelState::forChannel($this->apex)->auto_reply_enabled);

        // Enabled without text is refused; people without the permission are turned away.
        $this->actingAs($user)->put('/settings/messages/auto-reply', ['auto_reply_text' => ''] + $payload)->assertSessionHasErrors('auto_reply_text');
        $this->actingAs($other)->put('/settings/messages/auto-reply', $payload)->assertForbidden();
    }

    // ----------------------------------------------------------------- helpers

    private function sync(MessageThread $thread): void
    {
        app(MessageSyncService::class)->refreshThread($thread->fresh());
    }

    private function thread(string $externalId = 'c1', string $kind = MessageThread::KIND_PRE_PURCHASE): MessageThread
    {
        return MessageThread::create([
            'sales_channel_id' => $this->apex->id,
            'external_thread_id' => $externalId,
            'thread_kind' => $kind,
            'buyer_username' => 'michael_c',
            'status' => MessageThread::STATUS_AWAITING_BUYER,
        ]);
    }

    /**
     * The conversation eBay returns: the given buyer messages, the last one sent $at (default 2 minutes ago).
     */
    private function buyerWrites(string ...$ids): void
    {
        $this->buyerWritesAt(null, ...$ids);
    }

    private function buyerWritesAt(?\Carbon\CarbonInterface $at, string ...$ids): void
    {
        $this->conversation = ['conversationType' => 'FROM_MEMBERS', 'messages' => []];
        foreach ($ids as $i => $id) {
            $when = ($at ?? now()->subMinutes(2))->copy()->addSeconds($i);
            $this->conversation['messages'][] = $this->raw($id, 'michael_c', 'apex_auto', 'Is this in stock?', $when);
        }
    }

    private function raw(string $id, string $from, string $to, string $body, \Carbon\CarbonInterface $at): array
    {
        return [
            'messageId' => $id, 'messageBody' => $body, 'senderUsername' => $from, 'recipientUsername' => $to,
            'readStatus' => false, 'createdDate' => $at->copy()->utc()->toIso8601String(),
        ];
    }

    private function settings(bool $closedAllWeek, bool $enabled = true, string $text = self::AUTO_TEXT): void
    {
        $day = $closedAllWeek ? null : ['open' => '00:00', 'close' => '23:59'];

        MessageSetting::query()->delete();
        MessageSetting::create([
            'timezone' => 'UTC',
            'office_hours' => array_fill_keys(MessageSetting::DAYS, $day),
            'auto_reply_enabled' => $enabled,
            'auto_reply_text' => $text,
            'auto_reply_cooldown_hours' => 12,
        ]);
    }
}
