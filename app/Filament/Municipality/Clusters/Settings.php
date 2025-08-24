<?php

namespace App\Filament\Municipality\Clusters;

use App\Enums\Role;
use Filament\Clusters\Cluster;

class Settings extends Cluster
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('municipality/clusters/settings.label');
    }

    public static function getClusterBreadcrumb(): string
    {
        return __('municipality/clusters/settings.label');
    }

    public static function canAccess(): bool
    {
        return in_array(auth()->user()->role, [Role::Admin, Role::MunicipalityAdmin]);
    }
}
