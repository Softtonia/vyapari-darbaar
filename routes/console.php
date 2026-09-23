<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('notification:process-scheduled')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('news:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping();

// PIB RSS Auto-Import: dispatch to queue every 4 hours
// The actual HTTP fetch and DB writes happen inside the queued job.
Schedule::command('news:import-pib-rss')
    ->everyFourHours()
    ->withoutOverlapping();

