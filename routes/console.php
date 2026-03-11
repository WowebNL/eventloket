<?php

use App\Jobs\CleanupExpiredInvites;
use App\Jobs\CleanupExports;
use App\Jobs\ProcessSyncGeometryOnMunicipality;
use App\Jobs\SendAdviceReminders;
use App\Models\Municipality;
use Illuminate\Support\Facades\Schedule;

// Sync with Kadaster
Schedule::call(function () {
    foreach (Municipality::all() as $municipality) {
        ProcessSyncGeometryOnMunicipality::dispatch($municipality);
    }
})->weekly();

Schedule::job(new SendAdviceReminders)->dailyAt('12:00');

Schedule::job(new CleanupExpiredInvites)->daily();
Schedule::job(new CleanupExports)->daily();

Schedule::command('sync:zaaktypen')->dailyAt('02:00');
