<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Check delivery status for shipped orders every 2 hours
Schedule::command('orders:check-delivery-status --limit=100')
    ->everyTwoHours()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/delivery-status.log'));
