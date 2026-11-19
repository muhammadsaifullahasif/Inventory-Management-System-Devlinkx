<?php

namespace Tests\Feature\Messaging;

use App\Models\Message;
use App\Models\MessageChannelState;
use App\Models\MessageSetting;
use App\Models\MessageThread;
use App\Models\SalesChannel;
use App\Models\User;
use App\Services\Ebay\EbayNotificationService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesMessagingTables;
use Tests\TestCase;

/**
 * The optional extras: eBay message notifications, outbound attachments and the order-page card.
 * The eBay API is always faked; nothing here reaches a real store.
 */
class MessageExtrasTest extends TestCase
{
    use CreatesMessagingTables;

    private const SEND = 'api.ebay.com/commerce/message/v1/send_message';
    private const HTTPS = 'https://localhost';

    private SalesChannel $apex;
    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createMessagingTables();
        $this->createAuthTables();
        Storage::fake('local');

        $this->apex = SalesChannel::create([
            'name' => 'Store 1 (Apex Auto Body Parts)',
            'type' => 'ebay',
            'client_id' => 'app-id',
            'client_secret' => 'cert-id',
            'external_account_ids' => ['apex_auto'],
            'user_scopes' => 'https://api.ebay.com/oauth/api_scope/commerce.message',
            'access_token' => 'tok',
            'access_token_expires_at' => now()->addHour(),
        ]);

        Permission::findOrCreate('view messages', 'web');
        Permission::findOrCreate('reply messages', 'web');
        $this->agent = User::forceCreate(['name' => 'Agent']);
        $this->agent->givePermissionTo(['view messages', 'reply messages']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // ----------------------------------------------------- eBay notifications

    public function test_message_notifications_are_recognised_by_event_or_by_root_element(): void
    {
        $this->assertTrue(EbayNotificationService::isMessageNotification('GetMyMessagesResponse', []));
        $this->assertTrue(EbayNotificationService::isMessageNotification('GetMemberMessagesResponse', []));
        $this->assertTrue(EbayNotificationService::isMessageNotification('Whatever', ['NotificationEventName' => 'AskSellerQuestion']));
        $this->assertFalse(EbayNotificationService::isMessageNotification('ItemSold', ['NotificationEventName' => 'ItemSold']));
    }

    public function test_a_signed_message_notification_refreshes_that_store_only(): void
    {
        config(['services.ebay.dev_id' => 'dev-id']);
        Http::fake(['api.ebay.com/commerce/message/v1/conversation*' => Http::response(['conversations' => [], 'total' => 0])]);

        $this->assertNull(MessageChannelState::where('sales_channel_id', $this->apex->id)->value('last_synced_at'));

        $this->postNotification('MyMessagesM2MMessage')->assertOk();

        $this->assertNotNull(
            MessageChannelState::where('sales_channel_id', $this->apex->id)->value('last_synced_at'),
            'the store was synced right after the notification'
        );
    }

    public function test_an_unsigned_message_notification_does_nothing(): void
    {
        config(['services.ebay.dev_id' => 'dev-id']);
        Http::fake();

        $this->postNotification('MyMessagesM2MMessage', signature: 'forged')->assertStatus(401);

        Http::assertNothingSent();
        $this->assertSame(0, MessageChannelState::count());
    }

    public function test_an_order_notification_does_not_start_a_message_sync(): void
    {
        config(['services.ebay.dev_id' => 'dev-id']);
        Http::fake();

        $this->postNotification('ItemMarkedShipped', root: 'ItemMarkedShipped');

        Http::assertNothingSent();
    }

    // ------------------------------------------------------------- attachments

    public function test_a_reply_can_carry_attachments_that_ebay_downloads_from_a_secure_link(): void
    {
        $thread = $this->thread();
        Http::fake([self::SEND => Http::response(['messageId' => 'out-1', 'createdDate' => now()->utc()->toIso8601String()])]);

        $res = $this->actingAs($this->agent)->post(self::HTTPS . "/messages/{$thread->id}/reply", [
            'body' => 'Here is the part diagram',
            'files' => [
                UploadedFile::fake()->create('Part Diagram.jpg', 120, 'image/jpeg'),
                UploadedFile::fake()->create('invoice.pdf', 80, 'application/pdf'),
            ],
        ], ['Accept' => 'application/json'])->assertOk();

        Http::assertSent(function (Request $r) {
            if (!str_ends_with($r->url(), '/send_message')) {
                return false;
            }
            $media = $r['messageMedia'] ?? [];

            return count($media) === 2
                && $media[0]['mediaType'] === 'IMAGE' && $media[0]['mediaName'] === 'Part Diagram.jpg'
                && $media[1]['mediaType'] === 'PDF'
                && str_starts_with($media[0]['mediaUrl'], self::HTTPS . '/message-files/');
        });

        // The browser sees name, type and link, never the disk path.
        $shown = $res->json('messages.0.attachments.0');
        $this->assertSame(['name', 'type', 'url'], array_keys($shown));

        $stored = Message::first()->attachments;
        $this->assertCount(2, $stored);
        Storage::disk('local')->assertExists($stored[0]['local_path']);
    }

    public function test_the_download_link_works_only_for_the_exact_file(): void
    {
        $thread = $this->thread();
        Http::fake([self::SEND => Http::response(['messageId' => 'out-1'])]);
        $this->actingAs($this->agent)->post(self::HTTPS . "/messages/{$thread->id}/reply", [
            'body' => 'See photo', 'files' => [UploadedFile::fake()->create('photo.png', 10, 'image/png')],
        ], ['Accept' => 'application/json'])->assertOk();

        $url = Message::first()->attachments[0]['url'];
        [$token, $name] = array_slice(explode('/', $url), -2);

        \Illuminate\Support\Facades\Auth::forgetGuards();   // eBay is not logged in
        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Type', 'image/png');

        $this->get("/message-files/{$token}/other.png")->assertNotFound();
        $this->get('/message-files/' . str_repeat('a', 40) . "/{$name}")->assertNotFound();
        $this->get("/message-files/{$token}/..%2F..%2F.env")->assertNotFound();
        $this->get("/message-files/short/{$name}")->assertNotFound();
    }

    public function test_attachments_are_refused_without_https_and_nothing_is_sent_or_stored(): void
    {
        $thread = $this->thread();
        Http::fake();

        $this->actingAs($this->agent)->post("http://localhost/messages/{$thread->id}/reply", [
            'body' => 'Photo', 'files' => [UploadedFile::fake()->create('a.jpg', 10, 'image/jpeg')],
        ], ['Accept' => 'application/json'])->assertStatus(422);

        Http::assertNothingSent();
        $this->assertSame(0, Message::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_only_safe_file_types_sizes_and_counts_are_accepted(): void
    {
        $thread = $this->thread();
        Http::fake();
        $post = fn (array $files) => $this->actingAs($this->agent)->post(self::HTTPS . "/messages/{$thread->id}/reply", ['body' => 'x', 'files' => $files], ['Accept' => 'application/json']);

        $post([UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream')])->assertStatus(422)->assertJsonValidationErrors('files.0');
        $post([UploadedFile::fake()->create('page.html', 10, 'text/html')])->assertStatus(422);
        $post([UploadedFile::fake()->create('huge.pdf', 6000, 'application/pdf')])->assertStatus(422);
        $post(array_map(fn ($i) => UploadedFile::fake()->create("p{$i}.jpg", 5, 'image/jpeg'), range(1, 6)))->assertStatus(422);

        Http::assertNothingSent();
        $this->assertSame(0, Message::count());
    }

    public function test_a_failed_send_keeps_its_attachments_for_the_retry(): void
    {
        $thread = $this->thread();
        Http::fake([self::SEND => Http::sequence()
            ->push(['errors' => [['errorId' => 20400, 'message' => 'Invalid conversation']]], 400)
            ->push(['messageId' => 'out-1'])]);

        $this->actingAs($this->agent)->post(self::HTTPS . "/messages/{$thread->id}/reply", [
            'body' => 'Photo', 'files' => [UploadedFile::fake()->create('a.jpg', 10, 'image/jpeg')],
        ], ['Accept' => 'application/json'])->assertStatus(502);

        $failed = Message::first();
        $this->assertSame('failed', $failed->delivery_status);
        $this->assertCount(1, $failed->attachments);

        $this->actingAs($this->agent)->postJson(self::HTTPS . "/messages/{$thread->id}/messages/{$failed->id}/retry")->assertOk();

        $this->assertSame('sent', $failed->fresh()->delivery_status);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/send_message') && count($r['messageMedia'] ?? []) === 1);
    }

    public function test_the_platforms_copy_of_our_message_does_not_wipe_our_attachment_record(): void
    {
        $thread = $this->thread();
        $thread->update(['external_thread_id' => 'c1']);
        Http::fake([
            self::SEND => Http::response(['messageId' => 'out-1', 'createdDate' => now()->utc()->toIso8601String()]),
            'api.ebay.com/commerce/message/v1/conversation/*' => Http::response([
                'conversationType' => 'FROM_MEMBERS',
                'messages' => [[
                    'messageId' => 'out-1', 'messageBody' => 'Photo', 'senderUsername' => 'apex_auto', 'recipientUsername' => 'michael_c',
                    'readStatus' => true, 'createdDate' => now()->utc()->toIso8601String(),
                ]],
            ]),
        ]);

        $this->actingAs($this->agent)->post(self::HTTPS . "/messages/{$thread->id}/reply", [
            'body' => 'Photo', 'files' => [UploadedFile::fake()->create('a.jpg', 10, 'image/jpeg')],
        ], ['Accept' => 'application/json'])->assertOk();

        app(\App\Services\Messaging\MessageSyncService::class)->refreshThread($thread->fresh());

        $this->assertSame(1, Message::count());
        $this->assertNotEmpty(Message::first()->attachments[0]['local_path'] ?? null);
    }

    public function test_pruning_a_conversation_also_removes_its_stored_files(): void
    {
        MessageSetting::create(['timezone' => 'UTC', 'office_hours' => [], 'auto_reply_cooldown_hours' => 12, 'retention_days' => 30]);
        $thread = $this->thread(MessageThread::STATUS_RESOLVED);
        $thread->update(['last_message_at' => now()->subDays(60)]);
        Http::fake([self::SEND => Http::response(['messageId' => 'out-1'])]);
        $this->actingAs($this->agent)->post(self::HTTPS . "/messages/{$thread->id}/reply", [
            'body' => 'Photo', 'files' => [UploadedFile::fake()->create('a.jpg', 10, 'image/jpeg')],
        ], ['Accept' => 'application/json'])->assertOk();
        MessageThread::whereKey($thread->id)->update(['status' => MessageThread::STATUS_RESOLVED, 'last_message_at' => now()->subDays(60)->toDateTimeString()]);
        $this->assertNotEmpty(Storage::disk('local')->allFiles());

        $this->artisan('messages:prune')->assertSuccessful();

        $this->assertNull(MessageThread::find($thread->id));
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    // ---------------------------------------------------------- order page card

    public function test_the_order_page_card_lists_the_linked_conversations_for_people_who_can_open_the_inbox(): void
    {
        $thread = $this->thread(MessageThread::STATUS_PENDING);
        $thread->forceFill(['buyer_username' => 'michael_c', 'unread_count' => 1, 'last_message_at' => now()])->save();
        $m = Message::create(['thread_id' => $thread->id, 'direction' => 'inbound', 'sender_kind' => 'buyer', 'body' => 'Where is my parcel?', 'delivery_status' => 'sent', 'sent_at' => now()]);
        $thread->setRelation('recentMessages', collect([$m]));

        $this->actingAs($this->agent);
        $html = view('orders.partials._messages-card', ['messageThreads' => collect([$thread->load('salesChannel')])->each(fn ($t) => $t->setRelation('recentMessages', collect([$m])))])->render();

        $this->assertStringContainsString('Conversations (1)', $html);
        $this->assertStringContainsString('Where is my parcel?', $html);
        $this->assertStringContainsString('Needs reply', $html);
        $this->assertStringContainsString('/messages?thread=' . $thread->id, $html);

        $empty = view('orders.partials._messages-card', ['messageThreads' => collect()])->render();
        $this->assertStringContainsString('No buyer conversations are linked', $empty);

        // Someone without the inbox permission gets nothing at all.
        $nobody = User::forceCreate(['name' => 'Nobody']);
        $this->actingAs($nobody);
        $this->assertSame('', trim(view('orders.partials._messages-card', ['messageThreads' => collect([$thread])])->render()));
    }

    // ----------------------------------------------------------------- helpers

    private function thread(string $status = MessageThread::STATUS_PENDING): MessageThread
    {
        return MessageThread::create([
            'sales_channel_id' => $this->apex->id,
            'external_thread_id' => 'c1',
            'thread_kind' => MessageThread::KIND_POST_PURCHASE,
            'buyer_username' => 'michael_c',
            'status' => $status,
        ]);
    }

    /**
     * An eBay Platform Notification as eBay sends it: a SOAP envelope with a signature computed from
     * the timestamp and the app's DevID / AppID / CertID.
     */
    private function postNotification(string $event, ?string $signature = null, string $root = 'GetMyMessagesResponse')
    {
        $timestamp = now()->utc()->format('Y-m-d\TH:i:s.000\Z');
        $signature ??= base64_encode(md5($timestamp . 'dev-id' . 'app-id' . 'cert-id', true));

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soapenv:Header><ebl:RequesterCredentials xmlns:ebl="urn:ebay:apis:eBLBaseComponents"><ebl:NotificationSignature xmlns:ebl="urn:ebay:apis:eBLBaseComponents">' . $signature . '</ebl:NotificationSignature></ebl:RequesterCredentials></soapenv:Header>'
            . '<soapenv:Body><' . $root . ' xmlns="urn:ebay:apis:eBLBaseComponents">'
            . '<Timestamp>' . $timestamp . '</Timestamp><NotificationEventName>' . $event . '</NotificationEventName>'
            . '<RecipientUserID>apex_auto</RecipientUserID>'
            . '</' . $root . '></soapenv:Body></soapenv:Envelope>';

        return $this->call('POST', '/api/ebay/webhook', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml);
    }
}
