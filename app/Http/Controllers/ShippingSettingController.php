<?php

namespace App\Http\Controllers;

use App\Models\ShippingSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Middleware\PermissionMiddleware;

class ShippingSettingController extends Controller
{
    public function __construct()
    {
        $this->middleware(PermissionMiddleware::using('edit shipping'), ['only' => ['edit', 'update']]);
    }

    public function edit(): View
    {
        $settings = ShippingSetting::current();

        return view('shipping.settings', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cutoff_hour' => ['required', 'integer', 'min:0', 'max:23'],
        ]);

        ShippingSetting::query()->first()?->update($validated)
            ?? ShippingSetting::query()->create($validated);

        return redirect()->route('shipping.settings.edit')->with('success', 'Shipping settings updated.');
    }
}
