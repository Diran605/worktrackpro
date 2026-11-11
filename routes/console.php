<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Runs often; each organisation's own "abandoned_session_close_time" setting decides when its sessions close.
Schedule::command('worktrack:sessions:close-abandoned')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('worktrack:timers:stop-abandoned')
    ->everyThirtyMinutes();

Schedule::command('worktrack:knowledge:stale-sections')
    ->weeklyOn(1, '08:00');

Schedule::command('worktrack:plans:generate-recurring')
    ->dailyAt('00:00');
