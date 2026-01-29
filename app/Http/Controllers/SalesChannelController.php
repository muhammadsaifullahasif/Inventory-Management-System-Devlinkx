<?php

namespace App\Http\Controllers;

use App\Models\SalesChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\EbayNotificationService;
use Spatie\Permission\Middleware\PermissionMiddleware;

class SalesChannelController extends Controller
{
    protected EbayNotificationService $notificationService;

    public function __construct(EbayNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
        $this->middleware(PermissionMiddleware::using('view sales_channels'), ['only' => ['index']]);
        $this->middleware(PermissionMiddleware::using('add sales_channels'), ['only' => ['create', 'store']]);
        $this->middleware(PermissionMiddleware::using('edit sales_channels'), ['only' => ['edit', 'update']]);
        $this->middleware(PermissionMiddleware::using('delete sales_channels'), ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $sales_channels = SalesChannel::orderBy('id', 'DESC')->paginate(25);
        return view('sales-channel.index', compact('sales_channels'));
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
            'name' => 'required|string|max:255',
            'client_id' => 'required|string|max:255',
            'client_secret' => 'required|string|max:255',
            'ru_name' => 'required|string|max:255',
            'user_scopes' => 'required|string'
        ]);

        try {
            // Store sales channel logic here
            $sales_channel = new SalesChannel();
            $sales_channel->name = $request->input('name');
            $sales_channel->client_id = $request->input('client_id');
            $sales_channel->client_secret = $request->input('client_secret');
            $sales_channel->ru_name = $request->input('ru_name');
            $sales_channel->user_scopes = $request->input('user_scopes');
            $sales_channel->save();

            session(['sales_channel_id' => $sales_channel->id]);

            return redirect(env('EBAY_AUTH_URL') . '?client_id=' . $sales_channel->client_id . '&response_type=code' . '&redirect_uri=' . $sales_channel->ru_name . '&scope=' . urlencode($sales_channel->user_scopes));

            // return redirect()->route('sales-channels.index')->with('success', 'Sales Channel created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'An error occurred while creating the sales channel.'])->withInput();
        }
    }
    
    public function ebay_callback(Request $request)
    {
        try {
            $code = request('code');

            $sales_channel_id = session('sales_channel_id');
            $sales_channel = SalesChannel::find($sales_channel_id);

            $response = Http::asForm()
                ->withBasicAuth($sales_channel->client_id, $sales_channel->client_secret)
                ->post(env('EBAY_TOKEN_URL'), [
                    'grant_type'   => 'authorization_code',
                    'code'         => $code,
                    'redirect_uri' => $sales_channel->ru_name,
                ]);

            $response_data = $response->json();

            $sales_channel->authorization_code = $code;
            $sales_channel->access_token = $response_data['access_token'];
            $sales_channel->access_token_expires_at = now()->addSeconds($response_data['expires_in']);
            $sales_channel->refresh_token = $response_data['refresh_token'];
            $sales_channel->refresh_token_expires_at = now()->addSeconds($response_data['refresh_token_expires_in']);
            $sales_channel->save();

            // Subscribe to eBay notifications for orders
            $notificationResult = $this->subscribeToEbayNotifications($sales_channel);

            $message = 'Sales Channel created or updated successfully.';
            if ($notificationResult['success']) {
                $message .= ' Order notifications enabled.';
            } else {
                $message .= ' Warning: Could not enable notifications - ' . ($notificationResult['error'] ?? 'Unknown error');
            }

            return redirect()->route('sales-channels.index')->with('success', $message);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('sales-channels.index')->with('error', 'Authorization code is missing or invalid.');
        } catch (\Exception $e) {
            Log::error('eBay callback error', ['error' => $e->getMessage()]);
            return redirect()->route('sales-channels.index')->with('error', 'Error during authorization: ' . $e->getMessage());
        }
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

            Log::info('eBay notifications subscribed successfully', [
                'sales_channel_id' => $salesChannel->id,
                'events_count' => count($result['events'] ?? []),
                'events' => $result['events'] ?? [],
            ]);

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
            'client_id' => 'required|string|max:255',
            'client_secret' => 'required|string|max:255',
            'ru_name' => 'required|string|max:255',
            'user_scopes' => 'required|string'
        ]);

        try {
            // Update sales channel logic here
            $sales_channel = SalesChannel::findOrFail($id);
            $sales_channel->name = $request->input('name');
            $sales_channel->client_id = $request->input('client_id');
            $sales_channel->client_secret = $request->input('client_secret');
            $sales_channel->ru_name = $request->input('ru_name');
            $sales_channel->user_scopes = $request->input('user_scopes');
            $sales_channel->save();

            session(['sales_channel_id' => $sales_channel->id]);

            return redirect(env('EBAY_AUTH_URL') . '?client_id=' . $sales_channel->client_id . '&response_type=code' . '&redirect_uri=' . $sales_channel->ru_name . '&scope=' . urlencode($sales_channel->user_scopes));

            // return redirect()->route('sales-channels.index')->with('success', 'Sales Channel updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'An error occurred while updating the sales channel.'])->withInput();
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
