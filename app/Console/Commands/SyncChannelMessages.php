<?php

namespace App\Console\Commands;

use App\Models\SalesChannel;
use App\Services\Messaging\MessageDriverManager;
use App\Services\Messaging\MessageSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class SyncChannelMessages extends Command
{
    protected $signature = 'messages:sync
                            {--channel= : Specific sales channel ID to sync}
                            {--from= : Re-read everything changed since this date (Y-m-d), for backfill or repair}
                            {--days= : Same as --from, as a number of days back}
                            {--include-system : Also sync platform system conversations (eBay payout and return notices)}';

    protected $description = 'Sync buyer messages from every connected sales channel into the Messages inbox';

    public function handle(MessageSyncService $sync, MessageDriverManager $drivers): int
    {
        $from = $this->resolveFrom();
        if ($from === false) {
            return 1;
        }

        $startedAt = microtime(true);

        $channels = SalesChannel::query()
            ->whereNotNull('refresh_token')
            ->whereNotNull('client_id')
            ->whereNotNull('client_secret')
            ->when($this->option('channel'), fn ($q, $id) => $q->where('id', $id))
            ->orderBy('id')
            ->get()
            ->filter(fn (SalesChannel $c) => $drivers->supports($c));

        if ($channels->isEmpty()) {
            $this->warn('No connected sales channels with messaging support found.');
            return 0;
        }

        $failed = 0;
        $totals = ['threads_seen' => 0, 'threads_created' => 0, 'threads_updated' => 0, 'messages_created' => 0, 'errors' => 0];

        foreach ($channels as $channel) {
            try {
                $r = $sync->syncChannel($channel, $from, (bool) $this->option('include-system'));
            } catch (Throwable $e) {
                $failed++;
                $this->error("  [ERROR] {$channel->name} (ID {$channel->id}): {$e->getMessage()}");
                continue;
            }

            if ($r['skipped']) {
                $this->line("  [SKIP] {$channel->name} (ID {$channel->id}): {$r['skipped']}");
                continue;
            }

            foreach ($totals as $key => $_) {
                $totals[$key] += $r[$key];
            }

            $line = "  [OK] {$channel->name} (ID {$channel->id}): {$r['threads_seen']} seen, {$r['threads_created']} new, "
                . "{$r['threads_updated']} updated, {$r['messages_created']} new message(s)";
            $r['errors'] ? $this->warn("{$line}, {$r['errors']} thread error(s)") : $this->line($line);
        }

        $this->newLine();
        // Timestamp and duration: the scheduler appends this output to messages-sync.log, which otherwise has no times.
        $this->info('[' . now()->toDateTimeString() . '] Message sync complete: '
            . "{$totals['threads_seen']} threads seen, {$totals['threads_created']} new, "
            . "{$totals['messages_created']} new messages" . ($failed ? ", {$failed} store(s) failed" : '')
            . sprintf('. (%.1fs)', microtime(true) - $startedAt));

        // Like the other syncs: fail the scheduled task only when every store failed.
        return $failed === $channels->count() ? 1 : 0;
    }

    /**
     * @return Carbon|null|false  false = invalid input (error already printed)
     */
    private function resolveFrom(): Carbon|null|false
    {
        try {
            if ($this->option('from')) {
                return Carbon::parse($this->option('from'))->startOfDay();
            }

            if ($this->option('days') !== null) {
                $days = (int) $this->option('days');
                if ($days < 1) {
                    $this->error('--days must be 1 or more.');
                    return false;
                }

                return now()->subDays($days);
            }
        } catch (Throwable) {
            $this->error('Could not read --from. Use a date like 2026-11-01.');
            return false;
        }

        return null;
    }
}
