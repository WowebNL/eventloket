<?php

namespace App\Filament\Clusters;

use App\Enums\Role;
use Filament\Clusters\Cluster;

class AdminSettings extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('admin/clusters/admin_settings.label');
    }

    public static function getClusterBreadcrumb(): string
    {
        return __('admin/clusters/admin_settings.label');
    }

    public static function canAccess(): bool
    {
        return in_array(auth()->user()->role, [Role::Admin, Role::MunicipalityAdmin]);
    }
}
