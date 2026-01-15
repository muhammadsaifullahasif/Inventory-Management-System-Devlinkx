<?php

namespace App\Http\Controllers;

use App\Models\SalesChannel;
use Illuminate\Http\Request;
use App\Services\EbayService;

class EbayController extends Controller
{
    public function __construct(protected EbayService $ebayService) {}

    /**
     * Get active listings (paginated)
     */
    public function getActiveListings(Request $request, string $id)
    {
        try {
            $salesChannel = $this->getSalesChannelWithValidToken($id);

            $listings = $this->ebayService->getActiveListings(
                $salesChannel,
                $request->input('page', 1),
                $request->input('per_page', 100)
            );

            return response()->json($listings);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get ALL active listings
     */
    public function getAllActiveListings(string $id)
    {
        try {
            $salesChannel = $this->getSalesChannelWithValidToken($id);
            $listings = $this->ebayService->getAllActiveListings($salesChannel);

            return response()->json($listings);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get unsold listings (paginated)
     */
    public function getUnsoldListings(Request $request, string $id)
    {
        try {
            $salesChannel = $this->getSalesChannelWithValidToken($id);

            $listings = $this->ebayService->getUnsoldListings(
                $salesChannel,
                $request->input('page', 1),
                $request->input('per_page', 100),
                $request->input('days', 60)
            );

            return response()->json($listings);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get ALL unsold listings
     */
    public function getAllUnsoldListings(Request $request, string $id)
    {
        try {
            $salesChannel = $this->getSalesChannelWithValidToken($id);
            $listings = $this->ebayService->getAllUnsoldListings(
                $salesChannel,
                $request->input('days', 60)
            );

            return response()->json($listings);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get single item details
     */
    public function getItemDetails(string $id, string $itemId)
    {
        try {
            $salesChannel = $this->getSalesChannelWithValidToken($id);
            $itemDetails = $this->ebayService->getItemDetails($salesChannel, $itemId);

            return response()->json($itemDetails);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Redirect user to eBay for authorization
     */
    public function redirectToEbay(string $id)
    {
        $salesChannel = SalesChannel::findOrFail($id);
        $state = $id . '|' . bin2hex(random_bytes(16));
        session(['ebay_oauth_state' => $state]);

        return redirect()->away($this->ebayService->getAuthorizationUrl($salesChannel, $state));
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

            if ($stateParam !== $sessionState) {
                throw new \Exception('Invalid state parameter');
            }

            $stateParts = explode('|', $stateParam);
            $salesChannelId = $stateParts[0] ?? null;

            if (!$salesChannelId) {
                throw new \Exception('Sales channel ID not found in state');
            }

            $salesChannel = SalesChannel::findOrFail($salesChannelId);

            if ($request->has('error')) {
                throw new \Exception('eBay authorization failed: ' . $request->input('error_description', $request->input('error')));
            }

            $code = $request->input('code');
            $tokenData = $this->ebayService->getUserAccessToken($salesChannel, $code);

            $salesChannel->authorization_code = $code;
            $salesChannel->access_token = $tokenData['access_token'];
            $salesChannel->access_token_expires_at = now()->addSeconds($tokenData['expires_in']);

            if (isset($tokenData['refresh_token'])) {
                $salesChannel->refresh_token = $tokenData['refresh_token'];
                if (isset($tokenData['refresh_token_expires_in'])) {
                    $salesChannel->refresh_token_expires_at = now()->addSeconds($tokenData['refresh_token_expires_in']);
                }
            }

            $salesChannel->save();

            return redirect()->route('sales-channels.index')
                ->with('success', 'Successfully connected to eBay!');
        } catch (\Exception $e) {
            \Log::error('eBay Callback Error', ['message' => $e->getMessage()]);

            return redirect()->route('sales-channels.index')
                ->with('error', 'eBay authorization failed: ' . $e->getMessage());
        }
    }

    /**
     * Get sales channel with valid token (refresh if needed)
     */
    private function getSalesChannelWithValidToken(string $id): SalesChannel
    {
        $salesChannel = SalesChannel::findOrFail($id);

        if ($this->isAccessTokenExpired($salesChannel)) {
            $salesChannel = $this->refreshAccessToken($salesChannel);
        }

        return $salesChannel;
    }

    /**
     * Check if access token is expired
     */
    private function isAccessTokenExpired(SalesChannel $salesChannel): bool
    {
        if (empty($salesChannel->access_token) || empty($salesChannel->access_token_expires_at)) {
            return true;
        }

        return now()->addMinutes(5)->greaterThanOrEqualTo($salesChannel->access_token_expires_at);
    }

    /**
     * Refresh access token
     */
    private function refreshAccessToken(SalesChannel $salesChannel): SalesChannel
    {
        if (empty($salesChannel->refresh_token)) {
            throw new \Exception('No refresh token available. Please re-authorize with eBay.');
        }

        if ($salesChannel->refresh_token_expires_at && now()->greaterThanOrEqualTo($salesChannel->refresh_token_expires_at)) {
            throw new \Exception('Refresh token has expired. Please re-authorize with eBay.');
        }

        $tokenData = $this->ebayService->refreshUserToken($salesChannel);

        $salesChannel->access_token = $tokenData['access_token'];
        $salesChannel->access_token_expires_at = now()->addSeconds($tokenData['expires_in']);

        if (isset($tokenData['refresh_token'])) {
            $salesChannel->refresh_token = $tokenData['refresh_token'];
            if (isset($tokenData['refresh_token_expires_in'])) {
                $salesChannel->refresh_token_expires_at = now()->addSeconds($tokenData['refresh_token_expires_in']);
            }
        }

        $salesChannel->save();

        \Log::info('eBay access token refreshed', [
            'sales_channel_id' => $salesChannel->id,
            'new_expires_at' => $salesChannel->access_token_expires_at,
        ]);

        return $salesChannel;
    }

    /**
     * Return error response
     */
    private function errorResponse(string $message, int $status = 500)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
