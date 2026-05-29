<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| LaraPanel Scheduled Tasks
|--------------------------------------------------------------------------
*/

// SSL auto-renewal: check daily at 3 AM (low traffic window)
Schedule::command('ssl:renew')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/ssl-renew.log'));

// Server metrics: persist a snapshot every 5 minutes for historical graphs
Schedule::call(function () {
    app(\App\Services\MonitoringService::class)->persistSnapshot();
})->name('metrics-snapshot')->everyFiveMinutes()->withoutOverlapping();

// Prune old metrics: keep only last 24 hours
Schedule::call(function () {
    app(\App\Services\MonitoringService::class)->pruneOldMetrics();
})->name('metrics-prune')->hourly();
