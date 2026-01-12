<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class EbayService
{
    public function getAccessToken()
    {
        try {
            $clientId = env('EBAY_CLIENT_ID');
            $clientSecret = env('EBAY_CLIENT_SECRET');
            $scope = env('EBAY_OAUTH_SCOPE');

            // Log credentials for debugging (remove in production)
            Log::info('eBay Auth Request', [
                'client_id' => $clientId,
                'has_secret' => !empty($clientSecret),
                'scope' => $scope
            ]);

            $response = Http::timeout(60)
                ->connectTimeout(30)
                ->withOptions([
                    'verify' => false, // Disable SSL verification for testing
                    'debug' => false,
                ])
                ->withHeaders([
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Basic ' . base64_encode($clientId . ':' . $clientSecret),
                ])
                ->asForm()
                ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                    'scope' => $scope,
                ]);

            // Log response for debugging
            Log::info('eBay Auth Response', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            if ($response->failed()) {
                Log::error('eBay Authentication Failed', [
                    'status' => $response->status(),
                    'error' => $response->json(),
                ]);
                throw new Exception('eBay authentication failed: ' . $response->body());
            }

            return $response->json()['access_token'] ?? null;
        } catch (Exception $e) {
            Log::error('eBay getAccessToken Error', [
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function searchItems($query)
    {
        try {
            $token = $this->getAccessToken();

            if (!$token) {
                throw new Exception('Failed to obtain eBay access token');
            }

            $response = Http::timeout(60)
                ->connectTimeout(30)
                ->withOptions([
                    'verify' => false,
                    'debug' => false,
                ])
                ->withToken($token)
                ->get('https://api.ebay.com/buy/browse/v1/item_summary/search', [
                    'q' => $query,
                    'limit' => 20,
                ]);

            Log::info('eBay Search Response', [
                'status' => $response->status(),
                'query' => $query,
            ]);

            if ($response->failed()) {
                Log::error('eBay Search Failed', [
                    'status' => $response->status(),
                    'error' => $response->json(),
                ]);
                throw new Exception('eBay search failed: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('eBay searchItems Error', [
                'message' => $e->getMessage(),
                'query' => $query,
            ]);
            throw $e;
        }
    }

    public function getInventoryItems($limit = 100, $offset = 0)
    {
        try {
            // Use user access token from session or env
            $token = session('ebay_access_token') ?? env('EBAY_USER_ACCESS_TOKEN');

            if (!$token) {
                throw new Exception('User not authorized. Please authorize with eBay first or set EBAY_USER_ACCESS_TOKEN in .env');
            }

            $response = Http::timeout(60)
                ->connectTimeout(30)
                ->withOptions([
                    'verify' => false,
                    'debug' => false,
                ])
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ])
                ->get('https://api.ebay.com/sell/inventory/v1/inventory_item', [
                    'limit' => $limit,
                    'offset' => $offset,
                ]);

            Log::info('eBay Get Inventory Items Response', [
                'status' => $response->status(),
                'limit' => $limit,
                'offset' => $offset,
            ]);

            if ($response->failed()) {
                Log::error('eBay Get Inventory Items Failed', [
                    'status' => $response->status(),
                    'error' => $response->json(),
                ]);
                throw new Exception('eBay get inventory items failed: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('eBay getInventoryItems Error', [
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function getActiveListings($limit = 100, $offset = 0)
    {
        try {
            // Use user access token from session or env
            $token = session('ebay_access_token') ?? env('EBAY_USER_ACCESS_TOKEN');

            if (!$token) {
                throw new Exception('User not authorized. Please authorize with eBay first or set EBAY_USER_ACCESS_TOKEN in .env');
            }

            $response = Http::timeout(60)
                ->connectTimeout(30)
                ->withOptions([
                    'verify' => false,
                    'debug' => false,
                ])
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
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
            ]);

            if ($response->failed()) {
                Log::error('eBay Get Active Listings Failed', [
                    'status' => $response->status(),
                    'error' => $response->json(),
                ]);
                throw new Exception('eBay get active listings failed: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('eBay getActiveListings Error', [
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function getAllListings($status = 'ALL', $limit = 100, $offset = 0)
    {
        try {
            // Use user access token from session or env
            $token = session('ebay_access_token') ?? env('EBAY_USER_ACCESS_TOKEN');

            if (!$token) {
                throw new Exception('User not authorized. Please authorize with eBay first or set EBAY_USER_ACCESS_TOKEN in .env');
            }

            // Build query parameters
            $params = [
                'limit' => $limit,
                'offset' => $offset,
            ];

            // Add status filter if not ALL
            if ($status !== 'ALL') {
                $params['status'] = $status; // ACTIVE, INACTIVE, ENDED, etc.
            }

            $response = Http::timeout(60)
                ->connectTimeout(30)
                ->withOptions([
                    'verify' => false,
                    'debug' => false,
                ])
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ])
                ->get('https://api.ebay.com/sell/inventory/v1/offer', $params);

            Log::info('eBay Get All Listings Response', [
                'status' => $response->status(),
                'filter_status' => $status,
                'limit' => $limit,
                'offset' => $offset,
            ]);

            if ($response->failed()) {
                Log::error('eBay Get All Listings Failed', [
                    'status' => $response->status(),
                    'error' => $response->json(),
                ]);
                throw new Exception('eBay get all listings failed: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('eBay getAllListings Error', [
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get user information to verify token and scopes
     */
    public function getUserInfo()
    {
        try {
            $token = $this->getAccessToken();

            if (!$token) {
                throw new Exception('Failed to obtain eBay access token');
            }

            $response = Http::timeout(60)
                ->connectTimeout(30)
                ->withOptions([
                    'verify' => false,
                    'debug' => false,
                ])
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                ])
                ->get('https://apiz.ebay.com/commerce/identity/v1/user/');

            Log::info('eBay Get User Info Response', [
                'status' => $response->status(),
            ]);

            if ($response->failed()) {
                Log::error('eBay Get User Info Failed', [
                    'status' => $response->status(),
                    'error' => $response->json(),
                ]);
                return null;
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('eBay getUserInfo Error', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Generate OAuth authorization URL for user consent
     */
    public function getAuthorizationUrl($state = null)
    {
        $clientId = env('EBAY_CLIENT_ID');
        $redirectUri = env('EBAY_REDIRECT_URI');
        $scopes = env('EBAY_USER_SCOPES');
        $state = $state ?? bin2hex(random_bytes(16));

        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $scopes,
            'state' => $state,
        ];

        return 'https://auth.ebay.com/oauth2/authorize?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     */
    public function getUserAccessToken($code)
    {
        try {
            $clientId = env('EBAY_CLIENT_ID');
            $clientSecret = env('EBAY_CLIENT_SECRET');
            $redirectUri = env('EBAY_REDIRECT_URI');

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
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                ]);

            Log::info('eBay User Token Response', [
                'status' => $response->status(),
            ]);

            if ($response->failed()) {
                Log::error('eBay User Token Failed', [
                    'status' => $response->status(),
                    'error' => $response->json(),
                ]);
                throw new Exception('eBay user token exchange failed: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('eBay getUserAccessToken Error', [
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Refresh user access token
     */
    public function refreshUserToken($refreshToken)
    {
        try {
            $clientId = env('EBAY_CLIENT_ID');
            $clientSecret = env('EBAY_CLIENT_SECRET');
            $scopes = env('EBAY_USER_SCOPES');

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
                    'refresh_token' => $refreshToken,
                    'scope' => $scopes,
                ]);

            Log::info('eBay Refresh Token Response', [
                'status' => $response->status(),
            ]);

            if ($response->failed()) {
                Log::error('eBay Refresh Token Failed', [
                    'status' => $response->status(),
                    'error' => $response->json(),
                ]);
                throw new Exception('eBay refresh token failed: ' . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('eBay refreshUserToken Error', [
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
