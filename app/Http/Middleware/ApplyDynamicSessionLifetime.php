<?php

namespace App\Http\Middleware;

use App\Models\GeneralSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs before StartSession so admin-configured timeout takes effect
 * without needing an app restart / .env change.
 */
class ApplyDynamicSessionLifetime
{
    public function handle(Request $request, Closure $next): Response
    {
        $minutes = (int) GeneralSetting::get('session_lifetime_minutes', config('session.lifetime', 120));

        if ($minutes > 0) {
            Config::set('session.lifetime', $minutes);
        }

        return $next($request);
    }
}
