<?php

namespace App\Services;

use App\Models\SalesChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class EbayService
{
    /**
     * Refresh user access token using credentials from SalesChannel
     */
    public function refreshUserToken(SalesChannel $salesChannel): array
    {
        try {
            $clientId = $salesChannel->client_id;
            $clientSecret = $salesChannel->client_secret;
            $scopes = $salesChannel->user_scopes;

            $response = Http::timeout(60)
                ->connectTimeout(30)
                ->withOptions([
                    'verify' => false,
                    'debug' => false,
                ])
                ->withHeaders([
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Basic ' . base64_encode($clientId . ':' . $clientSecret),
                ])
                ->asForm()
                ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $salesChannel->refresh_token,
                    'scope' => $scopes,
                ]);

            Log::info('eBay Refresh Token Response', [
                'status' => $response->status(),
                'sales_channel_id' => $salesChannel->id,
            ]);

            if ($response->failed()) {
                Log::error('eBay Refresh Token Failed', [
                    'status' => $response->status(),
                    'error' => $response->json(),
                    'sales_channel_id' => $salesChannel->id,
                ]);
                throw new Exception('eBay refresh token failed: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('eBay refreshUserToken Error', [
                'message' => $e->getMessage(),
                'sales_channel_id' => $salesChannel->id,
            ]);
            throw $e;
        }
    }

    /**
     * Get inventory items from eBay
     */
    public function getInventoryItems(SalesChannel $salesChannel, int $limit = 100, int $offset = 0): array
    {
        try {
            // $response = Http::timeout(60)
            //     ->connectTimeout(30)
            //     ->withOptions([
            //         'verify' => false,
            //         'debug' => false,
            //     ])
            //     ->withHeaders([
            //         'Authorization' => 'Bearer ' . $salesChannel->access_token,
            //         'Content-Type' => 'application/json',
            //         'Accept' => 'application/json',
            //         'Accept-Encoding' => 'gzip',
            //         'Accept-Language' => 'en-US',
            //         'Content-Language' => 'en-US',
            //     ])
            //     ->get('https://api.ebay.com/sell/inventory/v1/inventory_item', [
            //         'limit' => $limit,
            //         'offset' => $offset,
            //     ]);

            // $response = Http::withToken($salesChannel->access_token)
            //     ->get('https://api.ebay.com/sell/inventory/v1/inventory_item', [
            //         'limit' => $limit,
            //         'offset' => $offset,
            //     ]);

            $response = Http::withToken($salesChannel->access_token)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.ebay.com/sell/feed/v1/inventory_task', [
                    'feed_type' => 'LMS_ACTIVE_INVENTORY_REPORT',
                    'schemaVersion' => '1.0',
                    'marketplaceId' => ['EBAY_US'],
                ]);

            Log::info('eBay Get Inventory Items Response', [
                'data' => $response->json(),
                'status' => $response->status(),
                // 'limit' => $limit,
                // 'offset' => $offset,
                'sales_channel_id' => $salesChannel->id,
            ]);

            if ($response->failed()) {
                Log::error('eBay Get Inventory Items Failed', [
                    'status' => $response->status(),
                    'error' => $response->json(),
                    'sales_channel_id' => $salesChannel->id,
                ]);
                throw new Exception('eBay get inventory items failed: ' . $response->body());
            }

            $statusCode = $response->status();

            $locationHeader = $response->header('Location');
            preg_match('/inventory_task\/(.+)$/', $locationHeader, $matches);
            $taskId = $matches[1] ?? null;

            $task_response = Http::withToken($salesChannel->access_token)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->get("https://api.ebay.com/sell/feed/v1/task/{$taskId}");

            dd($task_response->status(), $task_response->json());

            // dd($statusCode, $taskId, $locationHeader);

            // return $response->json();
        } catch (Exception $e) {
            Log::error('eBay getInventoryItems Error', [
                'message' => $e->getMessage(),
                'sales_channel_id' => $salesChannel->id,
            ]);
            throw $e;
        }
    }

    /**
     * Get active listings/offers from eBay
     */
    public function getActiveListings(SalesChannel $salesChannel, int $limit = 100, int $offset = 0): array
    {
        try {
            $response = Http::timeout(60)
                ->connectTimeout(30)
                ->withOptions([
                    'verify' => false,
                    'debug' => false,
                ])
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $salesChannel->access_token,
                    'Content-Type' => 'application/json',
                ])
                ->get('https://api.ebay.com/sell/inventory/v1/offer', [
                    'limit' => $limit,
                    'offset' => $offset,
                ]);

            Log::info('eBay Get Active Listings Response', [
                'status' => $response->status(),
                'limit' => $limit,
                'offset' => $offset,
                'sales_channel_id' => $salesChannel->id,
            ]);

            if ($response->failed()) {
                Log::error('eBay Get Active Listings Failed', [
                    'status' => $response->status(),
                    'error' => $response->json(),
                    'sales_channel_id' => $salesChannel->id,
                ]);
                throw new Exception('eBay get active listings failed: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('eBay getActiveListings Error', [
                'message' => $e->getMessage(),
                'sales_channel_id' => $salesChannel->id,
            ]);
            throw $e;
        }
    }

    /**
     * Get all listings from eBay with optional status filter
     * Status options: ALL, PUBLISHED, UNPUBLISHED, ENDED
     */
    public function getAllListings(SalesChannel $salesChannel, string $status = 'ALL', int $limit = 100, int $offset = 0): array
    {
        try {
            $params = [
                'limit' => $limit,
                'offset' => $offset,
            ];

            if ($status !== 'ALL') {
                $params['status'] = $status;
            }

            $response = Http::timeout(60)
                ->connectTimeout(30)
                ->withOptions([
                    'verify' => false,
                    'debug' => false,
                ])
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $salesChannel->access_token,
                    'Content-Type' => 'application/json',
                ])
                ->get('https://api.ebay.com/sell/inventory/v1/offer', $params);

            Log::info('eBay Get All Listings Response', [
                'status' => $response->status(),
                'filter_status' => $status,
                'limit' => $limit,
                'offset' => $offset,
                'sales_channel_id' => $salesChannel->id,
            ]);

            if ($response->failed()) {
                Log::error('eBay Get All Listings Failed', [
                    'status' => $response->status(),
                    'error' => $response->json(),
                    'sales_channel_id' => $salesChannel->id,
                ]);
                throw new Exception('eBay get all listings failed: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('eBay getAllListings Error', [
                'message' => $e->getMessage(),
                'sales_channel_id' => $salesChannel->id,
            ]);
            throw $e;
        }
    }

    /**
     * Generate OAuth authorization URL for user consent
     */
    public function getAuthorizationUrl(SalesChannel $salesChannel, string $state = null): string
    {
        $state = $state ?? bin2hex(random_bytes(16));

        $params = [
            'client_id' => $salesChannel->client_id,
            'redirect_uri' => $salesChannel->ru_name,
            'response_type' => 'code',
            'scope' => $salesChannel->user_scopes,
            'state' => $state,
        ];

        return 'https://auth.ebay.com/oauth2/authorize?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     */
    public function getUserAccessToken(SalesChannel $salesChannel, string $code): array
    {
        try {
            $response = Http::timeout(60)
                ->connectTimeout(30)
                ->withOptions([
                    'verify' => false,
                    'debug' => false,
                ])
                ->withHeaders([
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Basic ' . base64_encode($salesChannel->client_id . ':' . $salesChannel->client_secret),
                ])
                ->asForm()
                ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $salesChannel->ru_name,
                ]);

            Log::info('eBay User Token Response', [
                'status' => $response->status(),
                'sales_channel_id' => $salesChannel->id,
            ]);

            if ($response->failed()) {
                Log::error('eBay User Token Failed', [
                    'status' => $response->status(),
                    'error' => $response->json(),
                    'sales_channel_id' => $salesChannel->id,
                ]);
                throw new Exception('eBay user token exchange failed: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('eBay getUserAccessToken Error', [
                'message' => $e->getMessage(),
                'sales_channel_id' => $salesChannel->id,
            ]);
            throw $e;
        }
    }
}
