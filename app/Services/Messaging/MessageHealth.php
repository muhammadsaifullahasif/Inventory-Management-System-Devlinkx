<?php

namespace App\Services\Messaging;

use App\Models\MessageChannelState;
use App\Models\MessageThread;
use App\Models\SalesChannel;
use App\Notifications\MessageStoreNeedsReauthorizationNotification;
use App\Support\NotificationRecipients;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * One place that answers "is message sync working for each store?". Used by the inbox chip,
 * the re-authorization banner and the System Health card, so they can never disagree.
 */
class MessageHealth
{
    public const HEALTHY_WITHIN_MINUTES = 15;

    public const OK = 'ok';
    public const NEVER = 'never';
    public const STALE = 'stale';
    public const FAILING = 'failing';
    public const REAUTH = 'reauthorize';

    public function __construct(private MessageDriverManager $drivers) {}

    /**
     * The platform refused a messaging call because the store lacks the permission. Flags the store (the
     * inbox banner and the health card read this) and tells the admins once, when the flag is first raised.
     * Never throws: it runs inside sync and send paths that must carry on.
     */
    public function reportMissingScope(SalesChannel $channel, string $error): void
    {
        try {
            if (!MessageChannelState::forChannel($channel)->markNeedsReauthorization($error)) {
                return; // already flagged, admins already told
            }

            $admins = NotificationRecipients::admins();
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new MessageStoreNeedsReauthorizationNotification($channel->id, $channel->name));
            }
        } catch (\Throwable $e) {
            Log::channel('messages')->warning('Could not record that a store needs re-authorization', ['sales_channel_id' => $channel->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * One row per store that has messaging switched on.
     *
     * @return Collection<int,array{id:int,name:string,short_name:string,status:string,reason:?string,last_synced_at:?\Carbon\CarbonInterface,last_error_at:?\Carbon\CarbonInterface,needs_reauthorization:bool,auto_reply_enabled:bool}>
     */
    public function stores(): Collection
    {
        $cutoff = now()->subMinutes(self::HEALTHY_WITHIN_MINUTES);

        return SalesChannel::query()
            ->whereIn('type', $this->drivers->supportedTypes())
            ->with('messageState')
            ->orderBy('name')
            ->get()
            ->reject(fn (SalesChannel $c) => $c->messageState && !$c->messageState->messaging_enabled) // off on purpose
            ->map(function (SalesChannel $channel) use ($cutoff) {
                $state = $channel->messageState;
                $lacksScope = !str_contains((string) $channel->user_scopes, 'commerce.message');
                $reauth = $lacksScope || (bool) $state?->needs_reauthorization;

                [$status, $reason] = match (true) {
                    $reauth => [self::REAUTH, $lacksScope
                        ? 'This store has no messaging permission yet. Add the commerce.message scope and re-authorize it.'
                        : 'eBay refused access to messages. Re-authorize this store.'],
                    (bool) $state?->last_error => [self::FAILING, $state->last_error],
                    !$state?->last_synced_at => [self::NEVER, 'Not synced yet'],
                    $state->last_synced_at->lt($cutoff) => [self::STALE, 'No successful sync in the last ' . self::HEALTHY_WITHIN_MINUTES . ' minutes'],
                    default => [self::OK, null],
                };

                return [
                    'id' => $channel->id,
                    'name' => $channel->name,
                    'short_name' => preg_match('/\(([^)]+)\)\s*$/', $channel->name, $m) ? trim($m[1]) : $channel->name,
                    'status' => $status,
                    'reason' => $reason,
                    'last_synced_at' => $state?->last_synced_at,
                    'last_error_at' => $state?->last_error_at,
                    'needs_reauthorization' => $reauth,
                    'auto_reply_enabled' => (bool) $state?->auto_reply_enabled,
                ];
            })
            ->values();
    }

    /**
     * @return array{total:int, healthy:int, problems:array<int,array{name:string,reason:string}>}
     */
    public function summary(): array
    {
        $stores = $this->stores();
        $problems = $stores->where('status', '!=', self::OK)
            ->map(fn (array $s) => ['name' => $s['short_name'], 'reason' => (string) $s['reason']])
            ->values()
            ->all();

        return ['total' => $stores->count(), 'healthy' => $stores->count() - count($problems), 'problems' => $problems];
    }

    /**
     * Stores whose messages cannot sync until someone re-authorizes them with eBay.
     *
     * @return array<int,array{id:int,name:string}>
     */
    public function needingReauthorization(): array
    {
        return $this->stores()->where('status', self::REAUTH)
            ->map(fn (array $s) => ['id' => $s['id'], 'name' => $s['short_name']])
            ->values()
            ->all();
    }

    /**
     * Totals for the System Health card.
     *
     * @return array{threads:int, needing_reply:int, failed_sends:int}
     */
    public function workload(): array
    {
        return [
            'threads' => MessageThread::query()->count(),
            'needing_reply' => MessageThread::query()->where('status', MessageThread::STATUS_PENDING)->count(),
            'failed_sends' => \App\Models\Message::query()->where('delivery_status', \App\Models\Message::DELIVERY_FAILED)->count(),
        ];
    }
}
