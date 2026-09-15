<?php

namespace App\Http\Controllers;

use App\Models\GeneralSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Middleware\PermissionMiddleware;

class GeneralSettingController extends Controller
{
    public function __construct()
    {
        $this->middleware(PermissionMiddleware::using('manage general-settings'), ['only' => ['edit', 'update']]);
    }

    public function edit(): View
    {
        $settings = GeneralSetting::current();

        return view('settings.general', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['nullable', 'email'],
            'date_format' => ['required', Rule::in(['F d, Y', 'Y-m-d', 'm/d/Y', 'd/m/Y', 'M d, Y', 'd M Y'])],
            'week_start_day' => ['required', 'integer', 'between:0,6'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,svg', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            $current = GeneralSetting::current();
            if ($current->logo && file_exists(public_path($current->logo))) {
                @unlink(public_path($current->logo));
            }

            $logo = $request->file('logo');
            $logoName = time() . '.' . $logo->getClientOriginalExtension();
            $logo->move(public_path('uploads/logo'), $logoName);
            $validated['logo'] = 'uploads/logo/' . $logoName;
        }

        foreach ($validated as $key => $value) {
            GeneralSetting::set($key, $value);
        }

        return redirect()->route('settings.general.edit')->with('success', 'General settings updated.');
    }
}
