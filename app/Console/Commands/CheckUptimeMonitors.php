<?php

namespace App\Console\Commands;

use App\Models\Monitor;
use App\Services\UptimeMonitorService;
use Illuminate\Console\Command;

class CheckUptimeMonitors extends Command
{
    protected $signature = 'monitor:check-uptime';

    protected $description = 'Check uptime (and SSL, where enabled) for all active monitors that are due';

    public function handle(UptimeMonitorService $service): int
    {
        $monitors = Monitor::active()->due()->get();

        if ($monitors->isEmpty()) {
            $this->info('No monitors due for a check.');
            return 0;
        }

        $up = 0;
        $down = 0;

        foreach ($monitors as $monitor) {
            $service->check($monitor);
            $monitor->fresh()->uptime_status === 'up' ? $up++ : $down++;
        }

        $this->info("Checked {$monitors->count()} monitor(s): {$up} up, {$down} down.");

        return 0;
    }
}
