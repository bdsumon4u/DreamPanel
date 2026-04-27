<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::useCache('file');

Schedule::command('queue:work --queue=high,default,low --tries=3 --delay=60 --timeout=600 --stop-when-empty')
    ->everyMinute()
    ->runInBackground()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/queue-'.date('Y-m-d').'.log'));

// Schedule::command('sites:status-checks')
//     ->dailyAt('02:00')
//     ->timezone('Asia/Dhaka')
//     ->runInBackground()
//     ->withoutOverlapping()
//     ->appendOutputTo(storage_path('logs/status-'.date('Y-m-d').'.log'));

Schedule::command('sites:delete-files')
    ->dailyAt('04:00')
    ->timezone('Asia/Dhaka')
    ->runInBackground()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/delete-'.date('Y-m-d').'.log'));
