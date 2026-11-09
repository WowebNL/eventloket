<?php

use App\Jobs\CleanupExpiredEventFormDrafts;
use App\Jobs\CleanupExpiredInvites;
use App\Jobs\CleanupExports;
use App\Jobs\Notificaties\RenewZgwAbonnementen;
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

Schedule::command('zgw:prune-request-logs')->daily();

// Rotate Open Notificaties webhook tokens before they expire
Schedule::job(new RenewZgwAbonnementen)->daily();

// Verify that incoming notifications can still reach us, and report anything
// that is not in order. Runs early in the morning so a fault that appeared
// overnight is already reported when the working day starts. Everything this
// covers fails without raising an exception of its own, so without this run a
// broken notification pipeline stays indistinguishable from a quiet one.
Schedule::command('app:check-zgw-notificaties')->dailyAt('07:00');
