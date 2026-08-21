<?php

namespace App\Http\Controllers;

use App\Jobs\RunBackupJob;
use App\Models\BackupSetting;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BackupController extends Controller
{
    public function index(): View
    {
        $diskName = config('backup.backup.destination.disks.0', 'backups');
        $backupName = config('backup.backup.name');
        $disk = Storage::disk($diskName);

        $files = collect($disk->exists($backupName) ? $disk->allFiles($backupName) : [])
            ->filter(fn ($path) => str_ends_with($path, '.zip'))
            ->map(fn ($path) => [
                'path' => $path,
                'filename' => basename($path),
                'size_mb' => round($disk->size($path) / 1024 / 1024, 2),
                'modified' => Carbon::createFromTimestamp($disk->lastModified($path)),
            ])
            ->sortByDesc('modified')
            ->values();

        $totalSizeMb = round($files->sum('size_mb'), 2);
        $newest = $files->first();

        // Same thresholds as config('backup.monitor_backups.0.health_checks')
        $healthChecks = config('backup.monitor_backups.0.health_checks', []);
        $maxAgeHours = 24 * (int) ($healthChecks[\Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class] ?? 1);
        $maxStorageMb = (int) ($healthChecks[\Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class] ?? 5000);

        $ageHours = $newest ? $newest['modified']->diffInHours(now()) : null;
        $healthy = $newest && $ageHours <= $maxAgeHours && $totalSizeMb <= $maxStorageMb;

        $backupSettings = BackupSetting::current();

        $schedule = [
            ['task' => 'backup:run', 'when' => 'Daily at 02:00', 'purpose' => 'Full DB dump + storage/app files'],
            ['task' => 'backup:monitor', 'when' => 'Daily at 02:30', 'purpose' => 'Flags stale/oversized/missing backups'],
            ['task' => 'backup:clean', 'when' => 'Weekly, Monday 03:00', 'purpose' => "Retention: {$backupSettings->keep_daily_backups_for_days} daily / {$backupSettings->keep_weekly_backups_for_weeks} weekly / {$backupSettings->keep_monthly_backups_for_months} monthly"],
        ];

        return view('backups.index', compact(
            'files', 'totalSizeMb', 'newest', 'healthy', 'ageHours', 'diskName', 'schedule', 'backupSettings'
        ));
    }

    public function run(): RedirectResponse
    {
        RunBackupJob::dispatch();

        return back()->with('success', 'Backup queued. This can take a few minutes for a full DB + files dump — refresh this page to see it once it lands.');
    }

    public function monitor(): RedirectResponse
    {
        Artisan::call('backup:monitor');

        return back()->with('success', 'Health check run. Failures/stale backups are logged at critical level' . (config('sentry.dsn') ? ' and sent to Sentry.' : '.'));
    }

    public function download(string $filename): Response
    {
        $diskName = config('backup.backup.destination.disks.0', 'backups');
        $backupName = config('backup.backup.name');
        $path = $backupName.'/'.basename($filename);

        abort_unless(Storage::disk($diskName)->exists($path), 404);

        return Storage::disk($diskName)->download($path);
    }

    public function destroy(string $filename): RedirectResponse
    {
        $diskName = config('backup.backup.destination.disks.0', 'backups');
        $backupName = config('backup.backup.name');
        $path = $backupName.'/'.basename($filename);

        Storage::disk($diskName)->delete($path);

        return back()->with('success', "Deleted {$filename}.");
    }
}
