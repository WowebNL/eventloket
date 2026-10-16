<?php

namespace App\Filament\Municipality\Clusters;

use App\Enums\Role;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use UnitEnum;

class Archiving extends Cluster
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static string|UnitEnum|null $navigationGroup = 'Overig';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('municipality/clusters/archiving.label');
    }

    public static function getClusterBreadcrumb(): string
    {
        return __('municipality/clusters/archiving.label');
    }

    public static function canAccess(): bool
    {
        return in_array(auth()->user()->role, [Role::ArchiveCoordinator, Role::ArchiveReviewer]);
    }
}
