<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\AuditSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Moves audit_logs rows older than the configured retention window into
 * audit_log_archives, chunked so memory stays flat regardless of table
 * size and each chunk's move+delete is atomic (a crash mid-run can't
 * duplicate or drop rows).
 */
class ArchiveAuditLogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $settings = AuditSetting::current();

        if (! $settings->archive_enabled) {
            return;
        }

        $cutoff = now()->subDays($settings->retention_days);
        $movedTotal = 0;

        AuditLog::where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(500, function ($chunk) use (&$movedTotal) {
                $rows = $chunk->map(function (AuditLog $log) {
                    $attributes = $log->getAttributes();
                    unset($attributes['id']);

                    return $attributes;
                })->all();

                DB::transaction(function () use ($rows, $chunk) {
                    DB::table('audit_log_archives')->insert($rows);
                    AuditLog::whereIn('id', $chunk->pluck('id'))->delete();
                });

                $movedTotal += count($rows);
            });

        Log::info("ArchiveAuditLogsJob: moved {$movedTotal} row(s) older than {$settings->retention_days} days to audit_log_archives.");
    }
}
