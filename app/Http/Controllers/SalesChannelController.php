<?php

namespace App\Http\Controllers;

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
        $params = [
            'client_id' => env('EBAY_CLIENT_ID'),
            'redirect_uri' => env('EBAY_RU_NAME'),
            'response_type' => 'code',
            'scope' => env('EBAY_USER_SCOPES'),
        ];

        return redirect('https://auth.ebay.com/oauth2/authorize' . '?client_id=' . env('EBAY_CLIENT_ID') . '&response_type=code' . '&redirect_uri=' . env('EBAY_RU_NAME') . '&scope=' . urlencode(env('EBAY_USER_SCOPES')));
    }
    
    public function ebay_callback(Request $request)
    {
        $code = request('code');
        
        $response = Http::asForm()
            ->withBasicAuth(env('EBAY_CLIENT_ID'), env('EBAY_CLIENT_SECRET'))
            ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                'grant_type'   => 'authorization_code',
                'code'         => $code,
                'redirect_uri' => env('EBAY_RU_NAME'),
            ]);
        
        dd($response->status(), $response->json());
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
