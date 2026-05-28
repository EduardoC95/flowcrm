<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('followups:send-due')->everyThirtyMinutes();
Schedule::command('automations:run')->hourly();
Schedule::command('ai:analyze-commercial')->dailyAt('08:00');
