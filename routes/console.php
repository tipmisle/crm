<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// A single `php artisan schedule:run` every minute (cron) drives all of
// these — see docs/production-launch.md. withoutOverlapping() guards the
// everyMinute() reminder command in case a run takes longer than a minute;
// onOneServer() makes every entry safe if the app is ever deployed on more
// than one instance (uses the cache's atomic-lock support — the default
// `database` cache driver provides this, see config/cache.php).
Schedule::command('app:send-due-follow-up-reminders')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('demos:cleanup')->hourly()->onOneServer();
Schedule::command('workspaces:purge-expired')->daily()->onOneServer();
Schedule::command('exports:purge-expired')->hourly()->onOneServer();
