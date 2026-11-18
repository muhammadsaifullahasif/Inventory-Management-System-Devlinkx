<?php

namespace App\Console\Commands;

use App\Models\SalesChannel;
use App\Services\Messaging\ThreadLinker;
use Illuminate\Console\Command;

/**
 * Re-checks threads that have no product or order yet. The regular sync only re-checks the last
 * 14 days; use this once after the matching rules change, or after importing many listings.
 * Changes only message_threads (product_id / order_id / thread_kind); never touches listings or orders.
 */
class RelinkMessageThreads extends Command
{
    protected $signature = 'messages:relink
                            {--days=90 : How far back to re-check, by last message date}
                            {--channel= : Specific sales channel ID}';

    protected $description = 'Re-match message threads to products and orders after listings, SKUs or orders changed';

    public function handle(ThreadLinker $linker): int
    {
        $days = (int) $this->option('days');
        if ($days < 1) {
            $this->error('--days must be 1 or more.');
            return 1;
        }

        $channels = SalesChannel::query()
            ->when($this->option('channel'), fn ($q, $id) => $q->where('id', $id))
            ->orderBy('id')
            ->get();

        $total = 0;
        foreach ($channels as $channel) {
            $changed = $linker->relinkUnlinked($channel, limit: 5000, windowDays: $days);
            $total += $changed;

            if ($changed) {
                $this->line("  {$channel->name} (ID {$channel->id}): {$changed} thread(s) newly linked");
            }
        }

        $this->info("Done: {$total} thread(s) newly linked (last {$days} days).");

        return 0;
    }
}
