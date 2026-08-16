<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:send-due-follow-up-reminders')->everyMinute();
Schedule::command('demos:cleanup')->hourly();
Schedule::command('workspaces:purge-expired')->daily();
Schedule::command('exports:purge-expired')->hourly();
