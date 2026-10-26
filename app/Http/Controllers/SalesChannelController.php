<?php

namespace App\Http\Controllers;

use App\Models\SalesChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\Ebay\EbayNotificationService;
use App\Services\Ebay\EbayApiClient;
use App\Services\SalesChannelAccountService;
use Spatie\Permission\Middleware\PermissionMiddleware;

class SalesChannelController extends Controller
{
    protected EbayNotificationService $notificationService;
    protected SalesChannelAccountService $accountService;
    protected EbayApiClient $apiClient;

    public function __construct(EbayNotificationService $notificationService, SalesChannelAccountService $accountService, EbayApiClient $apiClient)
    {
        $this->notificationService = $notificationService;
        $this->accountService = $accountService;
        $this->apiClient = $apiClient;
        $this->middleware(PermissionMiddleware::using('view sales-channels'), ['only' => ['index', 'show', 'checkNotificationStatus']]);
        $this->middleware(PermissionMiddleware::using('add sales-channels'), ['only' => ['create', 'store']]);
        $this->middleware(PermissionMiddleware::using('edit sales-channels'), ['only' => ['edit', 'update', 'resubscribeNotifications', 'disableNotifications']]);
        $this->middleware(PermissionMiddleware::using('delete sales-channels'), ['only' => ['destroy']]);
        // OAuth return from eBay when connecting or re-authorising a channel
        $this->middleware(PermissionMiddleware::using('add sales-channels|edit sales-channels'), ['only' => ['ebay_callback']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = SalesChannel::query();

        // Filter by search term (name)
        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        // Filter by connection status (has valid tokens)
        if ($request->filled('status')) {
            if ($request->status === 'connected') {
                $query->whereNotNull('access_token')
                      ->where('access_token_expires_at', '>', now());
            } elseif ($request->status === 'disconnected') {
                $query->where(function ($q) {
                    $q->whereNull('access_token')
                      ->orWhere('access_token_expires_at', '<=', now());
                });
            }
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->input('per_page', 25);
        $sales_channels = $query->paginate($perPage)->withQueryString();
        return view('sales-channel.index', compact('sales_channels', 'perPage'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('sales-channel.new');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
        ]);

        return $this->redirectToEbayLogin($request->input('name'));
    }

    /**
     * Send the user to eBay's sign-in/consent page for the shared developer app.
     * The channel itself is only created (or matched) once eBay sends the
     * seller back, so an abandoned login leaves nothing behind.
     */
    protected function redirectToEbayLogin(?string $name = null, ?SalesChannel $reconnect = null)
    {
        if (!$this->apiClient->isAppConfigured()) {
            return back()->withErrors(['error' => 'eBay app credentials are not configured. Set EBAY_CLIENT_ID, EBAY_CLIENT_SECRET, EBAY_RU_NAME and EBAY_DEV_ID in .env.'])->withInput();
        }

        $state = Str::random(40);

        session(['ebay_oauth' => [
            'state' => $state,
            'name' => $name,
            'sales_channel_id' => $reconnect?->id,
        ]]);

        return redirect()->away($this->apiClient->getAuthorizationUrl($state));
    }

    public function ebay_callback(Request $request)
    {
        $oauth = session()->pull('ebay_oauth');

        try {
            if (!$oauth || !hash_equals($oauth['state'], (string) $request->query('state'))) {
                throw new \Exception('Login session expired or did not match. Please try connecting again.');
            }

            $code = $request->query('code');
            if (!$code) {
                $error = $request->query('error_description') ?? $request->query('error') ?? 'No code received';
                Log::warning('eBay callback: No authorization code received', ['error' => $error]);
                throw new \Exception('Authorization failed: ' . $error);
            }

            $tokens = $this->apiClient->exchangeAuthorizationCode($code);

            $ebayUserId = $this->apiClient->getUserId($tokens['access_token']);
            if (!$ebayUserId) {
                throw new \Exception('Could not identify the eBay account that signed in.');
            }

            $reconnect = $oauth['sales_channel_id'] ? SalesChannel::findOrFail($oauth['sales_channel_id']) : null;
            $existing = $this->findChannelForEbayUser($ebayUserId);

            if ($reconnect && $reconnect->external_account_id && $reconnect->external_account_id !== $ebayUserId) {
                throw new \Exception("You signed in to eBay as \"{$ebayUserId}\", but \"{$reconnect->name}\" is connected to \"{$reconnect->external_account_id}\". Sign in with that account instead.");
            }
            if ($reconnect && $existing && !$existing->is($reconnect)) {
                throw new \Exception("The eBay account \"{$ebayUserId}\" is already connected as \"{$existing->name}\".");
            }

            $salesChannel = $reconnect ?? $existing;
            $isNew = !$salesChannel;

            DB::transaction(function () use (&$salesChannel, $isNew, $oauth, $ebayUserId, $code, $tokens) {
                $salesChannel ??= new SalesChannel([
                    'name' => $oauth['name'] ?: $ebayUserId,
                    'type' => 'ebay',
                ]);

                $salesChannel->usesSharedEbayApp();
                $salesChannel->external_account_id = $ebayUserId;
                $salesChannel->external_account_ids = array_values(array_unique([...($salesChannel->external_account_ids ?? []), $ebayUserId]));
                $salesChannel->authorization_code = $code;
                $salesChannel->access_token = $tokens['access_token'];
                $salesChannel->access_token_expires_at = now()->addSeconds($tokens['expires_in']);
                $salesChannel->refresh_token = $tokens['refresh_token'];
                $salesChannel->refresh_token_expires_at = now()->addSeconds($tokens['refresh_token_expires_in']);
                $salesChannel->save();

                if ($isNew) {
                    $this->accountService->createAccountsForChannel($salesChannel);
                }
            });

            $notificationResult = $this->subscribeToEbayNotifications($salesChannel);

            $message = $isNew
                ? "eBay store \"{$salesChannel->name}\" ({$ebayUserId}) connected."
                : "eBay store \"{$salesChannel->name}\" ({$ebayUserId}) reconnected.";
            if ($notificationResult['success']) {
                $message .= ' Notifications enabled.';
            } else {
                $message .= ' Warning: Could not enable notifications - ' . ($notificationResult['error'] ?? 'Unknown error');
            }

            return redirect()->route('sales-channels.index')->with('success', $message);
        } catch (\Exception $e) {
            Log::error('eBay callback error', ['error' => $e->getMessage()]);
            return redirect()->route('sales-channels.index')->with('error', 'Error during authorization: ' . $e->getMessage());
        }
    }

    /**
     * eBay channel already linked to this seller account, if any.
     */
    protected function findChannelForEbayUser(string $ebayUserId): ?SalesChannel
    {
        return SalesChannel::where('type', 'ebay')
            ->where(function ($query) use ($ebayUserId) {
                $query->where('external_account_id', $ebayUserId)
                    ->orWhereJsonContains('external_account_ids', $ebayUserId);
            })
            ->first();
    }

    /**
     * Subscribe to eBay notifications for a sales channel
     * Subscribes to ALL available eBay notification events
     */
    protected function subscribeToEbayNotifications(SalesChannel $salesChannel): array
    {
        try {
            // Subscribe to ALL Platform Notifications (Trading API) events
            $result = $this->notificationService->subscribeToAllEvents($salesChannel);

            return ['success' => true, 'result' => $result];
        } catch (\Exception $e) {
            Log::error('Failed to subscribe to eBay notifications', [
                'sales_channel_id' => $salesChannel->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check current notification preferences from eBay
     * Shows which events are actually subscribed on eBay
     */
    public function checkNotificationStatus(string $id)
    {
        try {
            $salesChannel = SalesChannel::findOrFail($id);

            // Get notification preferences from eBay
            $result = $this->notificationService->getNotificationPreferences($salesChannel);

            $preferences = $result['preferences'] ?? [];
            $enabledEvents = $preferences['enabled_events'] ?? [];
            $applicationPrefs = $preferences['application_delivery_preferences'] ?? [];

            // Get the webhook URL
            $webhookUrl = $this->notificationService->getWebhookUrl($salesChannel);

            return response()->json([
                'success' => true,
                'sales_channel' => [
                    'id' => $salesChannel->id,
                    'name' => $salesChannel->name,
                ],
                'webhook_url' => $webhookUrl,
                'application_settings' => $applicationPrefs,
                'enabled_events_count' => count($enabledEvents),
                'enabled_events' => $enabledEvents,
                'all_preferences' => $preferences['user_delivery_preferences'] ?? [],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to check eBay notification status', [
                'sales_channel_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Manually re-subscribe to eBay notifications
     */
    public function resubscribeNotifications(string $id)
    {
        try {
            $salesChannel = SalesChannel::findOrFail($id);

            // Re-subscribe to all events
            $result = $this->notificationService->subscribeToAllEvents($salesChannel);

            return response()->json([
                'success' => true,
                'message' => 'Successfully re-subscribed to ' . count($result['events']) . ' notification events',
                'webhook_url' => $result['webhook_url'],
                'events' => $result['events'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to re-subscribe to eBay notifications', [
                'sales_channel_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Disable eBay notifications
     */
    public function disableNotifications(string $id)
    {
        try {
            $salesChannel = SalesChannel::findOrFail($id);

            $result = $this->notificationService->disablePlatformNotifications($salesChannel);

            return response()->json([
                'success' => true,
                'message' => 'Notifications disabled successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $sales_channel = SalesChannel::findOrFail($id);
        return view('sales-channel.edit', compact('sales_channel'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        try {
            $sales_channel = SalesChannel::findOrFail($id);
            $sales_channel->name = $request->input('name');
            $sales_channel->save();

            if ($request->has('reconnect')) {
                return $this->redirectToEbayLogin(reconnect: $sales_channel);
            }

            return redirect()->route('sales-channels.index')->with('success', 'Sales Channel updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update sales channel', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->withErrors(['error' => 'An error occurred while updating the sales channel: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $sales_channel = SalesChannel::findOrFail($id);
            $sales_channel->delete();

            return redirect()->route('sales-channels.index')->with('success', 'Sales Channel deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while deleting the sales channel: ' . $e->getMessage());
        }
    }
}
