<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('worktrack:sessions:close-abandoned')
    ->dailyAt(env('WORKTRACK_ABANDONED_SESSION_CLOSE_TIME', '20:00'));

Schedule::command('worktrack:timers:stop-abandoned')
    ->everyThirtyMinutes();

Schedule::command('worktrack:plans:generate-recurring')
    ->dailyAt('00:00');
