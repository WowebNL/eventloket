<?php

namespace App\Filament\Organiser\Clusters;

use App\Enums\OrganisationRole;
use App\Enums\OrganisationType;
use App\Models\Organisation;
use App\Models\Users\OrganiserUser;
use Filament\Clusters\Cluster;
use Filament\Facades\Filament;

class Settings extends Cluster
{
    protected static ?int $navigationSort = 10;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

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
        /** @var Organisation $tenant */
        $tenant = Filament::getTenant();

        /** @var OrganiserUser $user */
        $user = auth()->user();

        return $tenant->type != OrganisationType::Personal && $user->canAccessOrganisation($tenant->id, OrganisationRole::Admin);
    }
}
