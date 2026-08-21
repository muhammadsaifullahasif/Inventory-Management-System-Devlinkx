<?php

namespace App\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Runs a manual backup (triggered from the Backups admin page) on the
 * queue instead of the web request, since a full DB + storage/app dump
 * can take longer than a typical request timeout.
 */
class RunBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 1800; // 30 minutes

    public function handle(): void
    {
        $exitCode = Artisan::call('backup:run');

        Log::info('Manual backup:run finished', [
            'output' => Artisan::output(),
        ]);

        // Only prune logs once the zip actually has them safely in it.
        if ($exitCode === 0) {
            Artisan::call('logs:prune-backed-up');
        }
    }

    public function failed(Exception $exception): void
    {
        Log::critical('Manual backup:run job failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
