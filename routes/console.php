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

// Server metrics historical logging was removed during the Super Dashboard refactoring
