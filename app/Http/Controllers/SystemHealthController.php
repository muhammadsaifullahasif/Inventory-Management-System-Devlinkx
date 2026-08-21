<?php

namespace App\Http\Controllers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\View\View;

class SystemHealthController extends Controller
{
    protected const FAILED_JOBS_SORTABLE = ['job', 'queue', 'failed_at'];
    protected const SCHEDULE_SORTABLE = ['command', 'expression', 'next_due'];

    public function index(Request $request): View
    {
        $db = $this->checkDatabase();
        $queue = $this->checkQueue();

        $sortBy = in_array($request->get('sort_by'), self::FAILED_JOBS_SORTABLE) ? $request->get('sort_by') : 'failed_at';
        $sortOrder = $request->get('sort_order') === 'asc' ? 'asc' : 'desc';

        $failedJobsQuery = DB::table('failed_jobs');

        if ($sortBy === 'job') {
            $failedJobsQuery->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.displayName')) {$sortOrder}");
        } else {
            $failedJobsQuery->orderBy($sortBy, $sortOrder);
        }

        $failedJobs = $failedJobsQuery
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($job) => $this->decorateFailedJob($job));

        $failedJobsCount = DB::table('failed_jobs')->count();
        $pendingJobsCount = DB::table('jobs')->count();

        $logInfo = $this->checkLogs();

        $scheduleSortBy = in_array($request->get('schedule_sort_by'), self::SCHEDULE_SORTABLE) ? $request->get('schedule_sort_by') : null;
        $scheduleSortOrder = $request->get('schedule_sort_order') === 'desc' ? 'desc' : 'asc';
        $scheduledTasks = $this->scheduledTasks($scheduleSortBy, $scheduleSortOrder);

        $telescope = [
            'enabled' => (bool) config('telescope.enabled'),
        ];

        $sentry = [
            'configured' => (bool) config('sentry.dsn'),
        ];

        return view('system-health.index', compact(
            'db',
            'queue',
            'failedJobs',
            'failedJobsCount',
            'pendingJobsCount',
            'logInfo',
            'scheduledTasks',
            'telescope',
            'sentry',
            'sortBy',
            'sortOrder',
            'scheduleSortBy',
            'scheduleSortOrder',
        ));
    }

    public function retryFailedJob(string $id): RedirectResponse
    {
        Artisan::call('queue:retry', ['id' => [$id]]);

        return back()->with('success', "Job {$id} queued for retry.");
    }

    public function deleteFailedJob(string $id): RedirectResponse
    {
        Artisan::call('queue:forget', ['id' => $id]);

        return back()->with('success', "Failed job {$id} removed.");
    }

    public function clearFailedJobs(): RedirectResponse
    {
        Artisan::call('queue:flush');

        return back()->with('success', 'All failed jobs cleared.');
    }

    public function bulkRetryFailedJobs(Request $request): JsonResponse
    {
        $ids = (array) $request->input('ids', []);

        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => 'No jobs selected.'], 422);
        }

        Artisan::call('queue:retry', ['id' => $ids]);

        return response()->json(['success' => true, 'message' => count($ids) . ' job(s) queued for retry.']);
    }

    public function bulkDeleteFailedJobs(Request $request): JsonResponse
    {
        $ids = (array) $request->input('ids', []);

        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => 'No jobs selected.'], 422);
        }

        $deleted = DB::table('failed_jobs')->whereIn('uuid', $ids)->delete();

        return response()->json(['success' => true, 'message' => "{$deleted} job(s) deleted."]);
    }

    protected function checkDatabase(): array
    {
        $start = microtime(true);

        try {
            DB::connection()->getPdo();
            $ok = true;
            $error = null;
        } catch (\Throwable $e) {
            $ok = false;
            $error = $e->getMessage();
        }

        return [
            'ok' => $ok,
            'error' => $error,
            'connection' => config('database.default'),
            'latency_ms' => round((microtime(true) - $start) * 1000, 1),
        ];
    }

    protected function checkQueue(): array
    {
        $start = microtime(true);

        try {
            $size = Queue::connection()->size();
            $ok = true;
            $error = null;
        } catch (\Throwable $e) {
            $size = null;
            $ok = false;
            $error = $e->getMessage();
        }

        return [
            'ok' => $ok,
            'error' => $error,
            'connection' => config('queue.default'),
            'size' => $size,
            'latency_ms' => round((microtime(true) - $start) * 1000, 1),
        ];
    }

    protected function decorateFailedJob(object $job): object
    {
        $payload = json_decode($job->payload, true);
        $job->job_class = $payload['displayName'] ?? 'Unknown';
        $job->exception_excerpt = str($job->exception)->limit(300)->toString();

        return $job;
    }

    protected function checkLogs(): array
    {
        $path = storage_path('logs');

        $totalBytes = 0;
        $latestFile = null;
        $latestMtime = 0;

        if (File::isDirectory($path)) {
            // Top-level only — do NOT recurse. Subdirs here (ebay/orders/{date}/*.json etc.)
            // can hold tens of thousands of files; walking them on every page load
            // is what killed this endpoint on the shared host (resource-limit SIGKILL,
            // no exception, nothing logged).
            foreach (File::files($path) as $file) {
                $totalBytes += $file->getSize();

                if ($file->getMTime() > $latestMtime) {
                    $latestMtime = $file->getMTime();
                    $latestFile = $file->getFilename();
                }
            }
        }

        $activeChannel = config('logging.channels.stack.channels.0', config('logging.default'));

        return [
            'channel' => config('logging.default'),
            'driver' => config("logging.channels.{$activeChannel}.driver"),
            'retention_days' => config("logging.channels.{$activeChannel}.days"),
            'total_size_mb' => round($totalBytes / 1024 / 1024, 2),
            'latest_file' => $latestFile,
            'latest_modified' => $latestMtime ? \Carbon\Carbon::createFromTimestamp($latestMtime) : null,
        ];
    }

    protected function scheduledTasks(?string $sortBy, string $sortOrder): array
    {
        $schedule = app(Schedule::class);

        $tasks = collect($schedule->events())
            ->map(function ($event) {
                try {
                    $nextDue = $event->nextRunDate()->format('Y-m-d H:i:s');
                } catch (\Throwable $e) {
                    $nextDue = null;
                }

                return [
                    'command' => $event->getSummaryForDisplay(),
                    'expression' => $event->expression,
                    'next_due' => $nextDue,
                ];
            });

        if ($sortBy) {
            $tasks = $sortOrder === 'desc'
                ? $tasks->sortByDesc($sortBy)
                : $tasks->sortBy($sortBy);
        }

        return $tasks->values()->all();
    }
}
