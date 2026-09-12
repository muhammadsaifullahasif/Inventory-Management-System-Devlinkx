<?php

namespace App\Http\Controllers;

use App\Models\AuditSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Middleware\PermissionMiddleware;

class AuditSettingController extends Controller
{
    public function __construct()
    {
        $this->middleware(PermissionMiddleware::using('manage audit-settings'), ['only' => ['edit', 'update']]);
    }

    public function edit(): View
    {
        $settings = AuditSetting::current();

        return view('audit-logs.settings', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
        ]);

        $validated['archive_enabled'] = $request->boolean('archive_enabled');

        AuditSetting::query()->first()?->update($validated)
            ?? AuditSetting::query()->create($validated);

        return redirect()->route('audit-logs.settings.edit')->with('success', 'Audit log settings updated.');
    }
}
