<?php

namespace App\Console\Commands;

use App\Models\SalesChannel;
use App\Services\Ebay\EbayApiClient;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefreshEbayTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tokens:refresh-ebay
                            {--force : Force refresh even if not expiring soon}
                            {--channel= : Specific sales channel ID to refresh}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh eBay access tokens for all sales channels before they expire';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting eBay token refresh...');

        $query = SalesChannel::whereNotNull('refresh_token')
            ->whereNotNull('client_id')
            ->whereNotNull('client_secret');

        // Filter by specific channel if provided
        if ($channelId = $this->option('channel')) {
            $query->where('id', $channelId);
        }

        $channels = $query->get();

        if ($channels->isEmpty()) {
            $this->warn('No eBay sales channels found with refresh tokens.');
            return 0;
        }

        $this->info("Found {$channels->count()} eBay sales channel(s) to check.");

        $ebayClient = app(EbayApiClient::class);
        $refreshed = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($channels as $channel) {
            $this->line("Checking channel: {$channel->name} (ID: {$channel->id})");

            try {
                // Check if token needs refresh (expires within 30 minutes)
                $needsRefresh = $this->option('force') ||
                    empty($channel->access_token) ||
                    empty($channel->access_token_expires_at) ||
                    now()->addMinutes(30)->greaterThanOrEqualTo($channel->access_token_expires_at);

                if (!$needsRefresh) {
                    $expiresIn = now()->diffInMinutes($channel->access_token_expires_at);
                    $this->line("  → Token still valid for {$expiresIn} minutes. Skipping.");
                    $skipped++;
                    continue;
                }

                // Check if refresh token is expired
                if ($channel->refresh_token_expires_at && now()->greaterThanOrEqualTo($channel->refresh_token_expires_at)) {
                    $this->error("  → Refresh token has expired! Re-authorization required.");
                    Log::error('eBay refresh token expired', [
                        'sales_channel_id' => $channel->id,
                        'name' => $channel->name,
                        'refresh_token_expires_at' => $channel->refresh_token_expires_at,
                    ]);
                    $failed++;
                    continue;
                }

                $this->line("  → Refreshing token...");
                $ebayClient->refreshToken($channel);

                $this->info("  → Token refreshed successfully! New expiry: {$channel->access_token_expires_at}");
                $refreshed++;

            } catch (Exception $e) {
                $this->error("  → Failed to refresh token: {$e->getMessage()}");
                Log::error('eBay token refresh failed', [
                    'sales_channel_id' => $channel->id,
                    'name' => $channel->name,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Token refresh complete:");
        $this->line("  Refreshed: {$refreshed}");
        $this->line("  Skipped:   {$skipped}");
        $this->line("  Failed:    {$failed}");

        return $failed > 0 ? 1 : 0;
    }
}
