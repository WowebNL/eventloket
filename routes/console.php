<?php

use Illuminate\Support\Facades\Schedule;

// Sync with Kadaster
Schedule::call(function () {
    foreach (\App\Models\Municipality::all() as $municipality) {
        \App\Jobs\ProcessSyncGeometryOnMunicipality::dispatch($municipality);
    }
})->weekly();

Schedule::job(new \App\Jobs\SendAdviceReminders)->dailyAt('12:00');

Schedule::job(new \App\Jobs\CleanupExpiredInvites)->daily();
Schedule::job(new \App\Jobs\CleanupExports)->daily();

Schedule::command('sync:zaaktypen')->dailyAt('02:00');
