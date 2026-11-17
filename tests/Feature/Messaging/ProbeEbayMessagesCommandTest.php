<?php

namespace Tests\Feature\Messaging;

use App\Models\SalesChannel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProbeEbayMessagesCommandTest extends TestCase
{
    private const BASE = 'https://api.ebay.com/commerce/message/v1';

    private string $logDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Minimal table: the app's full migration set targets MySQL, so only what the command reads is created.
        Schema::create('sales_channels', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('type')->default('ebay');
            $t->string('external_account_id')->nullable();
            $t->text('external_account_ids')->nullable();
            $t->text('user_scopes')->nullable();
            $t->text('access_token')->nullable();
            $t->timestamp('access_token_expires_at')->nullable();
            $t->timestamps();
        });

        $this->logDir = storage_path('logs/ebay/messages/' . now()->format('Y-m-d'));
        File::deleteDirectory($this->logDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->logDir);
        parent::tearDown();
    }

    private function makeChannel(string $scopes): SalesChannel
    {
        return SalesChannel::create([
            'name' => 'Apex Auto Body Parts',
            'type' => 'ebay',
            'external_account_ids' => ['apex_auto'],
            'user_scopes' => $scopes,
            'access_token' => 'tok',
            'access_token_expires_at' => now()->addHour(),
        ]);
    }

    public function test_it_saves_raw_responses_and_confirms_usernames(): void
    {
        $channel = $this->makeChannel('https://api.ebay.com/oauth/api_scope/commerce.message');

        Http::fake([
            self::BASE . '/conversation/*' => Http::response([
                'conversationId' => 'conv-1',
                'messages' => [['messageId' => 'm-1', 'messageBody' => 'hi', 'senderUsername' => 'michael_c']],
            ]),
            self::BASE . '/conversation*' => function ($request) {
                if (str_contains($request->url(), 'FROM_MEMBERS')) {
                    return Http::response(['total' => 1, 'conversations' => [[
                        'conversationId' => 'conv-1',
                        'latestMessage' => ['senderUsername' => 'michael_c', 'recipientUsername' => 'apex_auto', 'createdDate' => '2026-11-18T13:42:00.000Z'],
                    ]]]);
                }

                return Http::response(['conversations' => []]);
            },
        ]);

        $this->artisan('messages:probe', ['channel' => $channel->id])
            ->expectsOutputToContain('Conversations returned: 1')
            ->expectsOutputToContain('Username check: OK')
            ->assertExitCode(0);

        $files = File::files($this->logDir);
        $this->assertCount(3, $files); // members list, ebay list, one conversation
        $this->assertStringContainsString('conv-1', File::get($files[0]->getPathname()));
        Http::assertNotSent(fn ($r) => $r->method() !== 'GET'); // read-only
    }

    public function test_it_stops_with_guidance_when_scope_is_missing(): void
    {
        $channel = $this->makeChannel('https://api.ebay.com/oauth/api_scope');
        Http::fake();

        $this->artisan('messages:probe', ['channel' => $channel->id])
            ->expectsOutputToContain('do not include commerce.message')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_it_rejects_unknown_channels(): void
    {
        $this->artisan('messages:probe', ['channel' => 999])
            ->expectsOutputToContain('not found')
            ->assertExitCode(1);
    }
}
