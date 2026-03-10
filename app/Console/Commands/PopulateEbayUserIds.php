<?php

namespace App\Console\Commands;

use App\Models\SalesChannel;
use App\Services\Ebay\EbayApiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class PopulateEbayUserIds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ebay:populate-user-ids';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch and store eBay UserID for all existing sales channels';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $channels = SalesChannel::whereNull('ebay_user_id')
            ->whereNotNull('access_token')
            ->get();

        if ($channels->isEmpty()) {
            $this->info('No sales channels need eBay UserID populated.');
            return 0;
        }

        $this->info("Found {$channels->count()} channels to update.");

        $client = app(EbayApiClient::class);

        foreach ($channels as $channel) {
            $this->line("Processing: {$channel->name} (ID: {$channel->id})");

            try {
                // Ensure valid token
                $channel = $client->ensureValidToken($channel);

                // Call GetUser API
                $xml = '<?xml version="1.0" encoding="utf-8"?>
                    <GetUserRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                        <ErrorLanguage>en_US</ErrorLanguage>
                    </GetUserRequest>';

                $response = $client->call($channel, 'GetUser', $xml);

                $userId = $response['User']['UserID'] ?? null;

                if ($userId) {
                    $channel->ebay_user_id = $userId;
                    $channel->save();

                    $this->info("  -> eBay UserID: {$userId}");
                } else {
                    $this->warn("  -> Could not extract UserID from response");
                }
            } catch (\Exception $e) {
                $this->error("  -> Error: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info('Done!');

        return 0;
    }
}
