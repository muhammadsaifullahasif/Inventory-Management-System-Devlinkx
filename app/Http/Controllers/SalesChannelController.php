<?php

namespace App\Http\Controllers;

use App\Models\SalesChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SalesChannelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('sales-channel.index');
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
        
        dd($response->status(), $response->json());

        return redirect()->route('sales-channels.index')->with('success', 'Sales Channel created successfully.');
        
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
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
