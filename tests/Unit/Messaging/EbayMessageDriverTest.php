<?php

namespace Tests\Unit\Messaging;

use App\Models\Message;
use App\Models\MessageThread;
use App\Models\SalesChannel;
use App\Services\Messaging\Contracts\MessageChannelDriver;
use App\Services\Messaging\Data\SendMessageData;
use App\Services\Messaging\Drivers\EbayMessageDriver;
use App\Services\Messaging\Exceptions\MissingScopeException;
use App\Services\Messaging\Exceptions\UnsupportedChannelException;
use App\Services\Messaging\MessageDriverManager;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Payload shapes below follow eBay's published Message API docs. Replace them with
 * the real captures from the Phase 0 spike once available.
 */
class EbayMessageDriverTest extends TestCase
{
    private const BASE = 'https://api.ebay.com/commerce/message/v1';

    private function channel(string $scopes = 'https://api.ebay.com/oauth/api_scope https://api.ebay.com/oauth/api_scope/commerce.message'): SalesChannel
    {
        $channel = new SalesChannel([
            'name' => 'Apex Auto Body Parts',
            'type' => 'ebay',
            'external_account_ids' => ['apex_auto'],
            'user_scopes' => $scopes,
            'access_token' => 'test-token',
            'access_token_expires_at' => now()->addHour(), // valid, so no refresh call is made
        ]);
        $channel->id = 7;

        return $channel;
    }

    private function driver(): EbayMessageDriver
    {
        return $this->app->make(EbayMessageDriver::class);
    }

    public function test_fetch_threads_maps_conversations_and_both_conversation_types(): void
    {
        Http::fake(function (Request $request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $q);

            if (($q['conversation_type'] ?? '') === 'FROM_MEMBERS') {
                return Http::response(['conversations' => [[
                    'conversationId' => 'conv-1',
                    'conversationTitle' => 'Will this fit a 2020 Civic?',
                    'conversationType' => 'FROM_MEMBERS',
                    'referenceId' => '1234567890',
                    'referenceType' => 'LISTING',
                    'unreadCount' => 2,
                    'createdDate' => '2026-11-18T13:00:00.000Z',
                    'latestMessage' => [
                        'messageId' => 'm-9',
                        'messageBody' => 'Hi, will this fender fit?',
                        'senderUsername' => 'michael_c',
                        'recipientUsername' => 'apex_auto',
                        'readStatus' => false,
                        'createdDate' => '2026-11-18T13:42:00.000Z',
                    ],
                ]]]);
            }

            // Live shape: system conversations have a title and NO latestMessage.
            return Http::response(['conversations' => [[
                'conversationId' => 'sys-1',
                'conversationStatus' => 'ACTIVE',
                'conversationTitle' => 'Return 5333331605: Issue refund',
                'conversationType' => 'FROM_EBAY',
                'createdDate' => '2026-11-17T10:00:00.000Z',
                'referenceId' => '188436749399',
                'referenceType' => 'LISTING',
                'unreadCount' => 1,
            ]]]);
        });

        // Default: buyer conversations only.
        $buyerOnly = iterator_to_array($this->driver()->fetchThreads($this->channel()), false);
        $this->assertCount(1, $buyerOnly);
        $this->assertSame('conv-1', $buyerOnly[0]->externalThreadId);
        $this->assertNull($buyerOnly[0]->subject, 'buyer-thread "title" is just the latest message text');
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'FROM_EBAY'));

        // Opt in to eBay system conversations.
        $threads = iterator_to_array($this->driver()->withSystemThreads()->fetchThreads($this->channel()), false);

        $this->assertCount(2, $threads);

        [$member, $system] = $threads;
        $this->assertSame('conv-1', $member->externalThreadId);
        $this->assertSame('michael_c', $member->buyerUsername);
        $this->assertSame('LISTING', $member->referenceType);
        $this->assertSame('1234567890', $member->externalReferenceId);
        $this->assertSame(2, $member->unreadCount);
        $this->assertFalse($member->hasFullHistory);
        $this->assertSame(MessageThread::KIND_UNKNOWN, $member->threadKind);
        $this->assertCount(1, $member->messages);
        $this->assertSame(Message::DIRECTION_INBOUND, $member->messages[0]->direction);
        $this->assertSame(Message::SENDER_BUYER, $member->messages[0]->senderKind);
        $this->assertFalse($member->messages[0]->isRead);

        $this->assertSame(MessageThread::KIND_PLATFORM_SYSTEM, $system->threadKind);
        $this->assertNull($system->buyerUsername);
        $this->assertSame('Return 5333331605: Issue refund', $system->subject);
        $this->assertSame('188436749399', $system->externalReferenceId);
        $this->assertSame(1, $system->unreadCount);
        $this->assertSame([], $system->messages, 'text needs a separate fetchThread call');
    }

    public function test_seller_messages_are_outbound(): void
    {
        Http::fake([self::BASE . '/conversation/*' => Http::response([
            'conversationId' => 'conv-1',
            'messages' => [
                ['messageId' => 'm-2', 'messageBody' => 'Please share your VIN', 'senderUsername' => 'APEX_AUTO', 'recipientUsername' => 'michael_c', 'createdDate' => '2026-11-18T13:50:00.000Z'],
                ['messageId' => 'm-1', 'messageBody' => 'Will it fit?', 'senderUsername' => 'michael_c', 'recipientUsername' => 'apex_auto', 'createdDate' => '2026-11-18T13:42:00.000Z'],
            ],
        ])]);

        $thread = new MessageThread([
            'external_thread_id' => 'conv-1',
            'thread_kind' => MessageThread::KIND_UNKNOWN,
            'external_reference_id' => '1234567890',
            'reference_type' => 'LISTING',
            'unread_count' => 3,
        ]);
        $data = $this->driver()->fetchThread($this->channel(), $thread);

        // Live detail responses carry no conversationId / referenceId / unreadCount: kept from the stored thread.
        $this->assertSame('conv-1', $data->externalThreadId);
        $this->assertSame('1234567890', $data->externalReferenceId);
        $this->assertSame('LISTING', $data->referenceType);
        $this->assertSame(3, $data->unreadCount);

        $this->assertTrue($data->hasFullHistory);
        $this->assertSame(['m-1', 'm-2'], array_map(fn ($m) => $m->externalMessageId, $data->messages), 'oldest first');
        $this->assertSame(Message::DIRECTION_OUTBOUND, $data->messages[1]->direction);
        $this->assertSame(Message::SENDER_SELLER, $data->messages[1]->senderKind);
        $this->assertTrue($data->messages[1]->isRead);
        $this->assertSame('michael_c', $data->buyerUsername);
    }

    public function test_send_posts_expected_payload_and_maps_response(): void
    {
        Http::fake([self::BASE . '/send_message' => Http::response([
            'messageId' => 'm-77',
            'conversationId' => 'conv-1',
            'createdDate' => '2026-11-18T14:00:00.000Z',
        ])]);

        $sent = $this->driver()->send($this->channel(), new SendMessageData(
            body: 'Please share your VIN',
            externalThreadId: 'conv-1',
        ));

        $this->assertSame('m-77', $sent->externalMessageId);
        $this->assertSame('conv-1', $sent->externalThreadId);

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === self::BASE . '/send_message'
            && $r['messageText'] === 'Please share your VIN'
            && $r['conversationId'] === 'conv-1'
            && $r->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_missing_scope_is_detected_before_any_http_call(): void
    {
        Http::fake();

        $this->expectException(MissingScopeException::class);

        try {
            iterator_to_array($this->driver()->fetchThreads($this->channel('https://api.ebay.com/oauth/api_scope')));
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_http_403_becomes_missing_scope_exception(): void
    {
        Http::fake([self::BASE . '/*' => Http::response(['errors' => [['errorId' => 1100, 'message' => 'Insufficient permissions']]], 403)]);

        $this->expectException(MissingScopeException::class);

        iterator_to_array($this->driver()->fetchThreads($this->channel()));
    }

    public function test_next_cursor_is_an_iso_timestamp_slightly_before_the_pass(): void
    {
        Http::fake([self::BASE . '/*' => Http::response(['conversations' => []])]);

        $driver = $this->driver();
        iterator_to_array($driver->fetchThreads($this->channel()));

        $cursor = \Carbon\Carbon::parse($driver->nextCursor());
        $this->assertTrue($cursor->lt(now()));
        $this->assertTrue($cursor->gt(now()->subMinutes(5)));
    }

    public function test_manager_resolves_ebay_driver_and_rejects_unknown_types(): void
    {
        $manager = $this->app->make(MessageDriverManager::class);

        $this->assertInstanceOf(EbayMessageDriver::class, $manager->forChannel($this->channel()));
        $this->assertTrue($manager->supports('ebay'));
        $this->assertContains(MessageChannelDriver::CAP_SEND, $manager->forChannel($this->channel())->capabilities());

        $other = new SalesChannel(['type' => 'shopify']);
        $this->assertFalse($manager->supports($other));

        $this->expectException(UnsupportedChannelException::class);
        $manager->forChannel($other);
    }
}
