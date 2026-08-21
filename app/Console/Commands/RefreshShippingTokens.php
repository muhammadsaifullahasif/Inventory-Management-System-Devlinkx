<?php

namespace App\Console\Commands;

use App\Models\Shipping;
use App\Notifications\ShippingTokenExpiredNotification;
use App\Services\FedexService;
use App\Services\UspsService;
use App\Support\NotificationRecipients;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class RefreshShippingTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tokens:refresh-shipping
                            {--force : Force refresh even if not expiring soon}
                            {--carrier= : Specific carrier ID to refresh}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh shipping carrier API tokens (FedEx, USPS, etc.) before they expire';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting shipping carrier token refresh...');

        $query = Shipping::where('status', 1)
            ->where('active_status', 1)
            ->where(function ($q) {
                $q->where('delete_status', 0)
                  ->orWhereNull('delete_status');
            });

        // Filter by specific carrier if provided
        if ($carrierId = $this->option('carrier')) {
            $query->where('id', $carrierId);
        }

        $carriers = $query->get();

        if ($carriers->isEmpty()) {
            $this->warn('No active shipping carriers found.');
            return 0;
        }

        $this->info("Found {$carriers->count()} shipping carrier(s) to check.");

        $refreshed = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($carriers as $carrier) {
            $this->line("Checking carrier: {$carrier->name} (ID: {$carrier->id}, Type: {$carrier->type})");

            try {
                // Check if token needs refresh (expires within 30 minutes)
                $needsRefresh = $this->option('force') ||
                    empty($carrier->access_token) ||
                    empty($carrier->access_token_expires_at) ||
                    now()->addMinutes(30)->greaterThanOrEqualTo($carrier->access_token_expires_at);

                if (!$needsRefresh) {
                    $expiresIn = now()->diffInMinutes($carrier->access_token_expires_at);
                    $this->line("  → Token still valid for {$expiresIn} minutes. Skipping.");
                    $skipped++;
                    continue;
                }

                $this->line("  → Refreshing token...");

                // Handle different carrier types
                $newToken = $this->refreshCarrierToken($carrier);

                if ($newToken) {
                    $carrier->refresh(); // Reload from DB
                    $this->info("  → Token refreshed successfully! New expiry: {$carrier->access_token_expires_at}");
                    $refreshed++;
                } else {
                    $this->error("  → Failed to obtain new token");
                    $this->notifyTokenFailure($carrier, 'Failed to obtain new token');
                    $failed++;
                }

            } catch (Exception $e) {
                $this->error("  → Failed to refresh token: {$e->getMessage()}");
                Log::error('Shipping token refresh failed', [
                    'carrier_id' => $carrier->id,
                    'name' => $carrier->name,
                    'type' => $carrier->type,
                    'error' => $e->getMessage(),
                ]);
                $this->notifyTokenFailure($carrier, $e->getMessage());
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

    /**
     * Notify admins that a carrier's token refresh failed.
     */
    protected function notifyTokenFailure(Shipping $carrier, string $errorMessage): void
    {
        Notification::send(
            NotificationRecipients::admins(),
            new ShippingTokenExpiredNotification($carrier->name, $carrier->type, $errorMessage)
        );
    }

    /**
     * Refresh token based on carrier type.
     */
    protected function refreshCarrierToken(Shipping $carrier): ?string
    {
        $type = strtolower($carrier->type ?? '');

        switch ($type) {
            case 'fedex':
                $service = new FedexService($carrier);
                return $service->refreshAccessToken();

            case 'usps':
                $service = new UspsService($carrier);
                return $service->refreshAccessToken();

            default:
                $this->warn("  → Unknown carrier type: {$type}");
                return null;
        }
    }
}
