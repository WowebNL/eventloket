<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\AdvisorPanelProvider;
use App\Providers\Filament\MunicipalityPanelProvider;
use App\Providers\Filament\OrganiserPanelProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    AdvisorPanelProvider::class,
    MunicipalityPanelProvider::class,
    OrganiserPanelProvider::class,
    HorizonServiceProvider::class,
];
