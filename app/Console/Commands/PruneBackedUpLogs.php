<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Deletes storage/logs files once they're captured in a backup zip.
 * Only intended to run right after a successful backup:run — files modified
 * today are always skipped, since they may still be open/actively written
 * (deleting a locked file fails hard on Windows) and aren't in today's zip yet.
 */
class PruneBackedUpLogs extends Command
{
    protected $signature = 'logs:prune-backed-up';

    protected $description = 'Delete storage/logs files older than today (run only after a successful backup)';

    public function handle(): int
    {
        $logsPath = storage_path('logs');
        $today = now()->format('Y-m-d');

        $deletedFiles = 0;
        $deletedBytes = 0;

        foreach (File::allFiles($logsPath) as $file) {
            if (date('Y-m-d', $file->getMTime()) === $today) {
                continue;
            }

            $deletedBytes += $file->getSize();
            File::delete($file->getPathname());
            $deletedFiles++;
        }

        $this->deleteEmptyDirectories($logsPath);

        $this->info("Pruned {$deletedFiles} log file(s), ".round($deletedBytes / 1024 / 1024, 2).' MB.');

        return self::SUCCESS;
    }

    protected function deleteEmptyDirectories(string $path): void
    {
        foreach (File::directories($path) as $directory) {
            $this->deleteEmptyDirectories($directory);

            if (empty(File::allFiles($directory)) && empty(File::directories($directory))) {
                File::deleteDirectory($directory);
            }
        }
    }
}
