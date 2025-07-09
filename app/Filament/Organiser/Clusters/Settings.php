<?php

namespace App\Filament\Organiser\Clusters;

use App\Enums\OrganisationRole;
use Filament\Clusters\Cluster;
use Filament\Facades\Filament;

class Settings extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    public static function getNavigationLabel(): string
    {
        return __('organiser/clusters/settings.label');
    }

    public static function getClusterBreadcrumb(): string
    {
        return __('organiser/clusters/settings.label');
    }

    public static function canAccess(): bool
    {
        /** @var \App\Models\Organisation $tenant */
        $tenant = Filament::getTenant();

        return auth()->user()->canAccessOrganisation($tenant->id, OrganisationRole::Admin);
    }
}
