<?php

namespace App\Http\Controllers;

use App\Models\BackupSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BackupSettingController extends Controller
{
    public function edit(): View
    {
        $settings = BackupSetting::current();

        return view('backups.settings', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'notification_email' => ['nullable', 'email'],
            'keep_daily_backups_for_days' => ['required', 'integer', 'min:1', 'max:365'],
            'keep_weekly_backups_for_weeks' => ['required', 'integer', 'min:0', 'max:104'],
            'keep_monthly_backups_for_months' => ['required', 'integer', 'min:0', 'max:60'],
        ]);

        $validated['schedule_enabled'] = $request->boolean('schedule_enabled');

        BackupSetting::query()->first()?->update($validated)
            ?? BackupSetting::query()->create($validated);

        return redirect()->route('backups.settings.edit')->with('success', 'Backup settings updated.');
    }
}
