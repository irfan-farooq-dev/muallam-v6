<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// File locks, so the scheduler works before the database (and its cache table) exists on a fresh server.
Schedule::useCache('file');

Schedule::command('posts:prune-images')->daily()->withoutOverlapping();
Schedule::command('notifications:prune')->daily()->withoutOverlapping();

// Finishes installing a newly uploaded release (does nothing when there is no new RELEASE file).
Schedule::command('site:deploy')->everyMinute()->withoutOverlapping()->appendOutputTo(storage_path('logs/deploy.log'));
