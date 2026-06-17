<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Re-score children/attendance via the ML microservice daily.
Schedule::command('ml:score')->dailyAt('03:00')->withoutOverlapping();
