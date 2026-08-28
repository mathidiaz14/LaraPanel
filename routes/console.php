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

// Server metrics collection and threshold alerts
Schedule::command('panel:collect-metrics')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/panel-metrics.log'));

// Check uptime for user configured monitors
Schedule::command('larapanel:uptime')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/larapanel-uptime.log'));

// Check uptime for active domains and alert if down
Schedule::command('panel:check-uptime')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/domains-uptime.log'));

// Auto-enroll uptime monitors for newly hosted domains / Docker containers
Schedule::command('larapanel:uptime-sync')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/larapanel-uptime-sync.log'));

// Backups Scheduler
Schedule::command('backups:run-scheduled')
    ->hourly()
    ->withoutOverlapping();

// Prune stale phpMyAdmin SSO tokens (DB credentials must not linger in /tmp)
Schedule::command('larapanel:cleanup-pma-sso')
    ->everyFiveMinutes()
    ->withoutOverlapping();
