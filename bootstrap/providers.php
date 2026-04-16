<?php

use App\Providers\AppServiceProvider;
use App\Providers\EventFormServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\AdvisorPanelProvider;
use App\Providers\Filament\MunicipalityPanelProvider;
use App\Providers\Filament\OrganiserPanelProvider;

return [
    AppServiceProvider::class,
    EventFormServiceProvider::class,
    AdminPanelProvider::class,
    AdvisorPanelProvider::class,
    MunicipalityPanelProvider::class,
    OrganiserPanelProvider::class,
];
