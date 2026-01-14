<?php

namespace App\Http\Controllers;

use App\Models\SalesChannel;
use Illuminate\Http\Request;
use App\Services\EbayService;
use Illuminate\Support\Facades\Http;

class EbayController extends Controller
{
    protected $ebayService;

    public function __construct(EbayService $ebayService)
    {
        $this->ebayService = $ebayService;
    }

    public function index(EbayService $ebay)
    {
        $results = $ebay->searchItems('fender');
        return response()->json($results);
    }

    public function getAccessToken(EbayService $ebay)
    {
        // $token = $ebay->getAccessToken();
        // return response()->json($token);

        $response = Http::asForm()
            ->withBasicAuth(env('EBAY_CLIENT_ID'), env('EBAY_CLIENT_SECRET'))
            ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                'grant_type' => 'authorization_code',
                'code' => $request->code,
                'redirect_uri' => env('EBAY_REDIRECT_URI')
            ]);

        return $response;
    }

    /**
     * Get all inventory items from eBay
     */
    public function getInventoryItems(string $id)
    {
        try {
            $sales_channels = SalesChannel::findOrFail($id);

            $response = Http::timeout(60)
                ->connectTimeout(30)
                ->withOptions([
                    'verify' => false,
                    'debug' => false,
                ])
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $sales_channels->access_token,
                    'Content-Type' => 'application/json',
                ])
                ->get('https://api.ebay.com/sell/inventory/v1/inventory_item');

            $inventoryItems = $response->json();

            // dd($inventoryItems);
            // $limit = $request->input('limit', 100);
            // $offset = $request->input('offset', 0);

            // $inventoryItems = $this->ebayService->getInventoryItems($limit, $offset);

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
     * Get active listings from eBay
     */
    public function getActiveListings(Request $request)
    {
        try {
            $limit = $request->input('limit', 100);
            $offset = $request->input('offset', 0);

            $activeListings = $this->ebayService->getActiveListings($limit, $offset);

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
     * Get all listings from eBay (active, ended, drafts)
     * Status options: ALL, PUBLISHED, UNPUBLISHED, ENDED
     */
    public function getAllListings(Request $request)
    {
        try {
            $status = $request->input('status', 'ALL'); // ALL, PUBLISHED, UNPUBLISHED, ENDED
            $limit = $request->input('limit', 100);
            $offset = $request->input('offset', 0);

            $allListings = $this->ebayService->getAllListings($status, $limit, $offset);

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
     * Get listings by specific status
     */
    public function getListingsByStatus(Request $request, $status)
    {
        try {
            $limit = $request->input('limit', 100);
            $offset = $request->input('offset', 0);

            $listings = $this->ebayService->getAllListings(strtoupper($status), $limit, $offset);

            return response()->json([
                'success' => true,
                'data' => $listings,
                'status' => strtoupper($status),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Redirect user to eBay for authorization
     */
    public function redirectToEbay()
    {
        $state = bin2hex(random_bytes(16));
        session(['ebay_oauth_state' => $state]);

        $authUrl = $this->ebayService->getAuthorizationUrl($state);
        return redirect()->away($authUrl);
    }

    /**
     * Handle OAuth callback from eBay
     */
    public function callback(Request $request)
    {
        // Log all incoming parameters for debugging
        \Log::info('eBay OAuth Callback', [
            'all_params' => $request->all(),
            'session_state' => session('ebay_oauth_state'),
        ]);

        try {
            // Verify state parameter
            if ($request->input('state') !== session('ebay_oauth_state')) {
                throw new \Exception('Invalid state parameter');
            }

            // Check for errors
            if ($request->has('error')) {
                throw new \Exception('eBay authorization failed: ' . $request->input('error_description', $request->input('error')));
            }

            // Exchange code for token
            $code = $request->input('code');
            $tokenData = $this->ebayService->getUserAccessToken($code);

            \Log::info('eBay Token Data Received', [
                'has_access_token' => isset($tokenData['access_token']),
                'has_refresh_token' => isset($tokenData['refresh_token']),
                'expires_in' => $tokenData['expires_in'] ?? null,
            ]);

            // Store tokens in session (or database for production)
            session([
                'ebay_access_token' => $tokenData['access_token'],
                'ebay_refresh_token' => $tokenData['refresh_token'] ?? null,
                'ebay_token_expires_at' => now()->addSeconds($tokenData['expires_in']),
            ]);

            \Log::info('eBay Tokens Stored in Session', [
                'session_has_token' => session()->has('ebay_access_token'),
            ]);

            return redirect()->route('ebay.inventory.items')
                ->with('success', 'Successfully connected to eBay!');
        } catch (\Exception $e) {
            \Log::error('eBay Callback Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('dashboard')
                ->with('error', 'eBay authorization failed: ' . $e->getMessage());
        }
    }
}
