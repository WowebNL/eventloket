<?php

use App\Jobs\CleanupExpiredEventFormDrafts;
use App\Jobs\CleanupExpiredInvites;
use App\Jobs\CleanupExports;
use App\Jobs\ProcessSyncGeometryOnMunicipality;
use App\Jobs\SendAdviceReminders;
use App\Models\Municipality;
use Illuminate\Support\Facades\Schedule;

// Capture Horizon queue metrics so the dashboard charts populate
Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Sync with Kadaster
Schedule::call(function () {
    foreach (Municipality::all() as $municipality) {
        ProcessSyncGeometryOnMunicipality::dispatch($municipality);
    }
})->weekly();

Schedule::job(new SendAdviceReminders)->dailyAt('12:00');

Schedule::job(new CleanupExpiredInvites)->daily();
Schedule::job(new CleanupExports)->daily();
Schedule::job(new CleanupExpiredEventFormDrafts)->daily();
