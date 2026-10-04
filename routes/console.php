<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Each installation runs the scheduler from cron (`* * * * * php artisan schedule:run`).
Schedule::command('erp:backup')->dailyAt('02:30')->withoutOverlapping();
