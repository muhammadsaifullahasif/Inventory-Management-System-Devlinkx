<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReleaseStaleJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:release-stale';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release state reserved jobs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $threshold = now()->subMintues(15)->timestamp;

        $updated = DB::table('jobs')
            ->where('queue', 'ebay-imports')
            ->whereNotNull('reserved_at')
            ->where('reserved_at', '<', $threshold)
            ->update([
                'reserved_at' => null, 
            ]);

        $this->info("Released {$updated} stale jobs.");
    }
}
