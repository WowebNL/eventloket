<?php

namespace App\Filament\Advisor\Clusters\Settings;

use App\Enums\AdvisoryRole;
use App\Models\Advisory;
use App\Models\Users\AdvisorUser;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;

class SettingsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 10;

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
        // Deny instead of failing when there is no current advisory or the
        // signed-in user is not an advisor.
        $tenant = Filament::getTenant();
        $user = auth()->user();

        return $tenant instanceof Advisory
            && $user instanceof AdvisorUser
            && $user->canAccessAdvisory($tenant->id, as: AdvisoryRole::Admin);
    }
}
