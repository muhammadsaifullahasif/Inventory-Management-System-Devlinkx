<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class SettingsController extends Controller
{
    public function index(): RedirectResponse
    {
        $user = auth()->user();

        return match (true) {
            $user->can('manage general-settings') => redirect()->route('settings.general.edit'),
            $user->can('manage audit-settings') => redirect()->route('settings.audit-log.edit'),
            $user->can('manage backup-settings') => redirect()->route('settings.backup.edit'),
            $user->can('edit shipping') => redirect()->route('settings.shipping.edit'),
            default => abort(403),
        };
    }
}
