<?php

namespace App\Http\Controllers;

use App\Models\SalesChannel;
use Illuminate\Http\Request;
use App\Services\EbayService;

class EbayController extends Controller
{
    protected $ebayService;

    public function __construct(EbayService $ebayService)
    {
        $this->ebayService = $ebayService;
    }

    /**
     * Get all inventory items from eBay for a specific sales channel
     */
    public function getInventoryItems(string $id)
    {
        try {
            $salesChannel = SalesChannel::findOrFail($id);

            // Check if access token is expired
            if ($this->isAccessTokenExpired($salesChannel)) {
                // Refresh the token
                $salesChannel = $this->refreshAccessToken($salesChannel);
            }

            $inventoryItems = $this->ebayService->getInventoryItems($salesChannel);

            return response()->json([
                'success' => true,
                'data' => $inventoryItems,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get active listings from eBay for a specific sales channel
     */
    public function getActiveListings(string $id)
    {
        try {
            $salesChannel = SalesChannel::findOrFail($id);

            // Check if access token is expired
            if ($this->isAccessTokenExpired($salesChannel)) {
                $salesChannel = $this->refreshAccessToken($salesChannel);
            }

            $activeListings = $this->ebayService->getActiveListings($salesChannel);

            return response()->json([
                'success' => true,
                'data' => $activeListings,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all listings from eBay (active, ended, drafts) for a specific sales channel
     * Status options: ALL, PUBLISHED, UNPUBLISHED, ENDED
     */
    public function getAllListings(Request $request, string $id)
    {
        try {
            $salesChannel = SalesChannel::findOrFail($id);

            // Check if access token is expired
            if ($this->isAccessTokenExpired($salesChannel)) {
                $salesChannel = $this->refreshAccessToken($salesChannel);
            }

            $status = $request->input('status', 'ALL');
            $limit = $request->input('limit', 100);
            $offset = $request->input('offset', 0);

            $allListings = $this->ebayService->getAllListings($salesChannel, $status, $limit, $offset);

            return response()->json([
                'success' => true,
                'data' => $allListings,
                'filter' => [
                    'status' => $status,
                    'limit' => $limit,
                    'offset' => $offset,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Redirect user to eBay for authorization for a specific sales channel
     */
    public function redirectToEbay(string $id)
    {
        $salesChannel = SalesChannel::findOrFail($id);

        $state = $id . '|' . bin2hex(random_bytes(16));
        session(['ebay_oauth_state' => $state]);

        $authUrl = $this->ebayService->getAuthorizationUrl($salesChannel, $state);
        return redirect()->away($authUrl);
    }

    /**
     * Handle OAuth callback from eBay
     */
    public function callback(Request $request)
    {
        \Log::info('eBay OAuth Callback', [
            'all_params' => $request->all(),
            'session_state' => session('ebay_oauth_state'),
        ]);

        try {
            $stateParam = $request->input('state');
            $sessionState = session('ebay_oauth_state');

            // Verify state parameter
            if ($stateParam !== $sessionState) {
                throw new \Exception('Invalid state parameter');
            }

            // Extract sales channel ID from state
            $stateParts = explode('|', $stateParam);
            $salesChannelId = $stateParts[0] ?? null;

            if (!$salesChannelId) {
                throw new \Exception('Sales channel ID not found in state');
            }

            $salesChannel = SalesChannel::findOrFail($salesChannelId);

            // Check for errors
            if ($request->has('error')) {
                throw new \Exception('eBay authorization failed: ' . $request->input('error_description', $request->input('error')));
            }

            // Exchange code for token
            $code = $request->input('code');
            $tokenData = $this->ebayService->getUserAccessToken($salesChannel, $code);

            \Log::info('eBay Token Data Received', [
                'has_access_token' => isset($tokenData['access_token']),
                'has_refresh_token' => isset($tokenData['refresh_token']),
                'expires_in' => $tokenData['expires_in'] ?? null,
                'sales_channel_id' => $salesChannel->id,
            ]);

            // Store tokens in database
            $salesChannel->authorization_code = $code;
            $salesChannel->access_token = $tokenData['access_token'];
            $salesChannel->access_token_expires_at = now()->addSeconds($tokenData['expires_in']);

            if (isset($tokenData['refresh_token'])) {
                $salesChannel->refresh_token = $tokenData['refresh_token'];
                // eBay refresh tokens typically expire in 18 months
                if (isset($tokenData['refresh_token_expires_in'])) {
                    $salesChannel->refresh_token_expires_at = now()->addSeconds($tokenData['refresh_token_expires_in']);
                }
            }

            $salesChannel->save();

            \Log::info('eBay Tokens Stored in Database', [
                'sales_channel_id' => $salesChannel->id,
                'access_token_expires_at' => $salesChannel->access_token_expires_at,
            ]);

            return redirect()->route('sales-channels.index')
                ->with('success', 'Successfully connected to eBay!');
        } catch (\Exception $e) {
            \Log::error('eBay Callback Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('sales-channels.index')
                ->with('error', 'eBay authorization failed: ' . $e->getMessage());
        }
    }

    /**
     * Check if the access token is expired
     */
    private function isAccessTokenExpired(SalesChannel $salesChannel): bool
    {
        if (empty($salesChannel->access_token) || empty($salesChannel->access_token_expires_at)) {
            return true;
        }

        // Consider token expired if it expires within the next 5 minutes (buffer time)
        return now()->addMinutes(5)->greaterThanOrEqualTo($salesChannel->access_token_expires_at);
    }

    /**
     * Refresh the access token using the refresh token
     */
    private function refreshAccessToken(SalesChannel $salesChannel): SalesChannel
    {
        if (empty($salesChannel->refresh_token)) {
            throw new \Exception('No refresh token available. Please re-authorize with eBay.');
        }

        // Check if refresh token is also expired
        if ($salesChannel->refresh_token_expires_at && now()->greaterThanOrEqualTo($salesChannel->refresh_token_expires_at)) {
            throw new \Exception('Refresh token has expired. Please re-authorize with eBay.');
        }

        $tokenData = $this->ebayService->refreshUserToken($salesChannel);

        // Update the sales channel with new tokens
        $salesChannel->access_token = $tokenData['access_token'];
        $salesChannel->access_token_expires_at = now()->addSeconds($tokenData['expires_in']);

        // Update refresh token if a new one is provided
        if (isset($tokenData['refresh_token'])) {
            $salesChannel->refresh_token = $tokenData['refresh_token'];
            if (isset($tokenData['refresh_token_expires_in'])) {
                $salesChannel->refresh_token_expires_at = now()->addSeconds($tokenData['refresh_token_expires_in']);
            }
        }

        $salesChannel->save();

        \Log::info('eBay access token refreshed for sales channel', [
            'sales_channel_id' => $salesChannel->id,
            'new_expires_at' => $salesChannel->access_token_expires_at,
        ]);

        return $salesChannel;
    }
}
