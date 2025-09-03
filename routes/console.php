<?php

use Illuminate\Support\Facades\Schedule;

// Sync with Kadaster
Schedule::call(function () {
    foreach (\App\Models\Municipality::all() as $municipality) {
        \App\Jobs\ProcessSyncGeometryOnMunicipality::dispatch($municipality);
    }
})->weekly();

Schedule::command('sync:zaaktypen')->dailyAt('02:00');
