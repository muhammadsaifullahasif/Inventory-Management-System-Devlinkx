<?php

namespace App\Http\Middleware;

use App\Models\SalesChannel;
use App\Support\AuditContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Populates the request-scoped AuditContext singleton so AuditLogger,
 * AuditObserver, and the auth event listeners all pick up the same
 * ip/user-agent/actor without re-deriving it. Registered for both the web
 * and api middleware groups in bootstrap/app.php.
 */
class CaptureAuditContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(AuditContext::class);

        $context->ipAddress = $request->ip();
        $context->userAgent = $request->userAgent();
        $context->url = $request->fullUrl();
        $context->httpMethod = $request->method();

        $user = $request->user();

        if ($user) {
            $context->actorType = $request->is('api/*') ? 'api' : 'user';
            $context->actorId = $user->id;
            $context->actorLabel = $user->name ?? $user->email;
        } else {
            $context->actorType = $request->is('api/*') ? 'webhook' : 'user';

            // Legacy eBay webhook routes carry the SalesChannel id in the
            // {id} route param — resolve it so the audit row shows which
            // channel sent the notification instead of just "webhook".
            $channelId = $request->route('id');

            if ($context->actorType === 'webhook' && $channelId) {
                $channel = SalesChannel::find($channelId);
                $context->actorLabel = $channel ? "Sales Channel: {$channel->name}" : null;
            }
        }

        return $next($request);
    }
}
