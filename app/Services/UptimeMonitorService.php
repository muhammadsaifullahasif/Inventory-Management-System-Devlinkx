<?php

namespace App\Services;

use App\Models\Monitor;
use App\Models\MonitorHistory;
use App\Notifications\MonitorDownNotification;
use App\Notifications\MonitorUpNotification;
use App\Support\NotificationRecipients;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Throwable;

class UptimeMonitorService
{
    public function check(Monitor $monitor): void
    {
        $this->checkUptime($monitor);

        if ($monitor->check_ssl && str_starts_with($monitor->url, 'https://')) {
            $this->checkSsl($monitor);
        }
    }

    protected function checkUptime(Monitor $monitor): void
    {
        $previousStatus = $monitor->uptime_status;
        $start = microtime(true);

        try {
            $response = Http::timeout($monitor->timeout_seconds)
                ->withUserAgent('Laravel Uptime Monitor')
                ->get($monitor->url);

            $responseTimeMs = (int) round((microtime(true) - $start) * 1000);
            $isUp = $response->status() < 400;

            $newStatus = $isUp ? 'up' : 'down';
            $failureReason = $isUp ? null : "HTTP status {$response->status()}";
        } catch (Throwable $e) {
            $responseTimeMs = (int) round((microtime(true) - $start) * 1000);
            $newStatus = 'down';
            $failureReason = $e->getMessage();
        }

        $monitor->uptime_last_checked_at = now();
        $monitor->uptime_check_response_time_ms = $responseTimeMs;
        $monitor->uptime_check_failure_reason = $failureReason;

        if ($newStatus !== $previousStatus) {
            $monitor->uptime_status = $newStatus;
            $monitor->uptime_status_changed_at = now();
        }

        $monitor->save();

        if ($newStatus !== $previousStatus && $previousStatus !== 'not_yet_checked') {
            $this->recordHistoryAndNotify($monitor, $newStatus, $responseTimeMs, $failureReason);
        } elseif ($previousStatus === 'not_yet_checked') {
            // First-ever check: log it for the timeline, but don't alert admins.
            MonitorHistory::create([
                'monitor_id' => $monitor->id,
                'status' => $newStatus,
                'response_time_ms' => $responseTimeMs,
                'message' => $newStatus === 'up' ? 'Initial check: up' : "Initial check: down ({$failureReason})",
                'created_at' => now(),
            ]);
        }
    }

    protected function recordHistoryAndNotify(Monitor $monitor, string $newStatus, ?int $responseTimeMs, ?string $failureReason): void
    {
        $message = $newStatus === 'down'
            ? "Went down: {$failureReason}"
            : 'Back up';

        MonitorHistory::create([
            'monitor_id' => $monitor->id,
            'status' => $newStatus,
            'response_time_ms' => $responseTimeMs,
            'message' => $message,
            'created_at' => now(),
        ]);

        $admins = NotificationRecipients::admins();

        if ($newStatus === 'down') {
            Notification::send($admins, new MonitorDownNotification($monitor));
        } else {
            $downtime = $this->describeDowntime($monitor);
            Notification::send($admins, new MonitorUpNotification($monitor, $downtime));
        }
    }

    protected function describeDowntime(Monitor $monitor): ?string
    {
        $lastHistory = $monitor->history()->where('status', 'down')->first();

        if (!$lastHistory) {
            return null;
        }

        return $lastHistory->created_at->diffForHumans(now(), true);
    }

    protected function checkSsl(Monitor $monitor): void
    {
        $host = parse_url($monitor->url, PHP_URL_HOST);
        $port = parse_url($monitor->url, PHP_URL_PORT) ?? 443;

        if (!$host) {
            $monitor->ssl_status = 'invalid';
            $monitor->ssl_check_failure_reason = 'Could not parse host from URL';
            $monitor->save();

            return;
        }

        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $client = @stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno,
            $errstr,
            $monitor->timeout_seconds,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$client) {
            $monitor->ssl_status = 'invalid';
            $monitor->ssl_check_failure_reason = $errstr ?: 'Could not open SSL connection';
            $monitor->save();

            return;
        }

        $params = stream_context_get_params($client);
        fclose($client);

        $cert = $params['options']['ssl']['peer_certificate'] ?? null;

        if (!$cert) {
            $monitor->ssl_status = 'invalid';
            $monitor->ssl_check_failure_reason = 'No certificate returned by host';
            $monitor->save();

            return;
        }

        $certInfo = openssl_x509_parse($cert);
        $expiresAt = $certInfo['validTo_time_t'] ?? null;

        if (!$expiresAt) {
            $monitor->ssl_status = 'invalid';
            $monitor->ssl_check_failure_reason = 'Could not read certificate expiry';
            $monitor->save();

            return;
        }

        $monitor->ssl_status = $expiresAt > time() ? 'valid' : 'invalid';
        $monitor->ssl_expiration_date = date('Y-m-d', $expiresAt);
        $monitor->ssl_check_failure_reason = null;
        $monitor->save();
    }
}
