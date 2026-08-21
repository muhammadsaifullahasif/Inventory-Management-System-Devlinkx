<?php

namespace App\Http\Controllers;

use App\Jobs\BrokenLinkCrawlJob;
use App\Models\CrawlRun;
use App\Models\Monitor;
use App\Services\UptimeMonitorService;
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
    protected const SCHEDULE_SORTABLE = ['command', 'expression', 'frequency', 'next_due'];

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

        $disk = $this->diskSpace();
        $securityChecks = $this->securityChecks();

        $monitor = Monitor::first();
        $monitorHistory = $monitor?->history()->limit(10)->get() ?? collect();
        $avgResponseMs = $monitor
            ? round((float) $monitor->history()->limit(20)->avg('response_time_ms'), 1)
            : null;

        $latestCrawlRun = CrawlRun::with('brokenLinks')->latest('id')->first();

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
            'disk',
            'securityChecks',
            'monitor',
            'monitorHistory',
            'avgResponseMs',
            'latestCrawlRun',
        ));
    }

    public function checkUptimeNow(UptimeMonitorService $service): RedirectResponse
    {
        $monitor = Monitor::first();

        if (!$monitor) {
            return back()->with('error', 'No monitor configured yet.');
        }

        $service->check($monitor);

        return back()->with('success', "Checked \"{$monitor->name}\" — status: {$monitor->fresh()->uptime_status}.");
    }

    public function runCrawlNow(): RedirectResponse
    {
        BrokenLinkCrawlJob::dispatch(150);

        return back()->with('success', 'Broken link crawl queued — results will appear here once the queue worker picks it up (usually within a few minutes).');
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

    protected function diskSpace(): array
    {
        $free = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());

        if (!$free || !$total) {
            return ['available' => false];
        }

        $percentFree = round(($free / $total) * 100, 1);

        return [
            'available' => true,
            'percent_free' => $percentFree,
            'free_gb' => round($free / 1024 ** 3, 1),
            'total_gb' => round($total / 1024 ** 3, 1),
            'level' => $percentFree < 5 ? 'danger' : ($percentFree < 15 ? 'warning' : 'success'),
        ];
    }

    protected function securityChecks(): array
    {
        $isProduction = app()->environment('production');

        $checks = [
            [
                'label' => 'Debug mode',
                'ok' => !$isProduction || !config('app.debug'),
                'message' => 'APP_DEBUG is on in production — stack traces are exposed to visitors.',
            ],
            [
                'label' => 'App key',
                'ok' => (bool) config('app.key'),
                'message' => 'APP_KEY is not set — sessions and encrypted data are not secure.',
            ],
            [
                'label' => 'HTTPS',
                'ok' => !$isProduction || str_starts_with((string) config('app.url'), 'https://'),
                'message' => 'APP_URL is not https:// in production.',
            ],
            [
                'label' => 'Secure session cookie',
                'ok' => !$isProduction || config('session.secure'),
                'message' => 'SESSION_SECURE_COOKIE is off in production — session cookie can be sent over plain HTTP.',
            ],
            [
                'label' => 'Mail delivery',
                'ok' => !$isProduction || !in_array(config('mail.default'), ['log', 'array']),
                'message' => 'MAIL_MAILER is "' . config('mail.default') . '" in production — outgoing email is silently dropped.',
            ],
        ];

        return $checks;
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
        // routes/console.php (where Schedule::command() calls live) is only
        // required inside Kernel::bootstrap(), which artisan calls but a web
        // request never does — so Schedule::events() would be empty here
        // without forcing it explicitly.
        app(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        $schedule = app(Schedule::class);

        $runs = DB::table('schedule_task_runs')->get()->keyBy('command');

        $tasks = collect($schedule->events())
            ->map(function ($event) use ($runs) {
                try {
                    $nextDue = $event->nextRunDate()->format('Y-m-d H:i:s');
                } catch (\Throwable $e) {
                    $nextDue = null;
                }

                $command = $event->getSummaryForDisplay();
                $run = $runs->get($command);
                $lastRanAt = $run?->last_ran_at ? \Carbon\Carbon::parse($run->last_ran_at) : null;

                // Overdue: never ran, or last ran before the previous point this
                // task was due to fire (5 min grace for scheduler-cycle drift).
                $overdue = false;
                try {
                    $previousDue = (new \Cron\CronExpression($event->expression))->getPreviousRunDate(now());
                    $overdue = !$lastRanAt || $lastRanAt->lt($previousDue->modify('-5 minutes'));
                } catch (\Throwable $e) {
                    // Unparseable expression — don't flag as overdue.
                }

                return [
                    'command' => $command,
                    'expression' => $event->expression,
                    'frequency' => $this->describeCronExpression($event->expression),
                    'next_due' => $nextDue,
                    'last_ran_at' => $lastRanAt?->format('Y-m-d H:i:s'),
                    'last_status' => $run?->status,
                    'overdue' => $overdue,
                ];
            });

        if ($sortBy) {
            $tasks = $sortOrder === 'desc'
                ? $tasks->sortByDesc($sortBy)
                : $tasks->sortBy($sortBy);
        }

        return $tasks->values()->all();
    }

    /**
     * Turn a 5-field cron expression into a plain-English frequency
     * (e.g. "Every 15 minutes", "Daily at 07:00", "Weekly on Monday at 03:00").
     * Falls back to the raw expression for anything not recognised.
     */
    protected function describeCronExpression(string $expression): string
    {
        $parts = preg_split('/\s+/', trim($expression));

        if (count($parts) !== 5) {
            return $expression;
        }

        [$minute, $hour, $day, $month, $weekday] = $parts;

        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $time = fn ($h, $m) => sprintf('%02d:%02d', $h, $m);

        // Every minute
        if ($minute === '*' && $hour === '*' && $day === '*' && $month === '*' && $weekday === '*') {
            return 'Every minute';
        }

        // Every N minutes
        if (preg_match('/^\*\/(\d+)$/', $minute, $m) && $hour === '*' && $day === '*' && $month === '*' && $weekday === '*') {
            return "Every {$m[1]} minutes";
        }

        // Every N hours (on the hour)
        if (is_numeric($minute) && preg_match('/^\*\/(\d+)$/', $hour, $m) && $day === '*' && $month === '*' && $weekday === '*') {
            return "Every {$m[1]} hours";
        }

        // Hourly
        if (is_numeric($minute) && $hour === '*' && $day === '*' && $month === '*' && $weekday === '*') {
            return 'Every hour, at minute ' . (int) $minute;
        }

        // Twice/multiple times daily at specific hours (comma list), any day
        if (is_numeric($minute) && str_contains($hour, ',') && $day === '*' && $month === '*' && $weekday === '*') {
            $times = collect(explode(',', $hour))->map(fn ($h) => $time((int) $h, (int) $minute))->implode(', ');

            return "Daily at {$times}";
        }

        // Daily at a specific time
        if (is_numeric($minute) && is_numeric($hour) && $day === '*' && $month === '*' && $weekday === '*') {
            return 'Daily at ' . $time((int) $hour, (int) $minute);
        }

        // Weekly on a specific day/time
        if (is_numeric($minute) && is_numeric($hour) && $day === '*' && $month === '*' && is_numeric($weekday)) {
            $dayName = $days[(int) $weekday % 7] ?? $weekday;

            return "Weekly on {$dayName} at " . $time((int) $hour, (int) $minute);
        }

        // Monthly on a specific day/time
        if (is_numeric($minute) && is_numeric($hour) && is_numeric($day) && $month === '*' && $weekday === '*') {
            return "Monthly on day {$day} at " . $time((int) $hour, (int) $minute);
        }

        return $expression;
    }
}
