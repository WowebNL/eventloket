<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityAdminUserResource\Pages;

use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityAdminUserResource;
use App\Filament\Shared\Resources\MunicipalityAdminUsers\Actions\MunicipalityAdminUserInviteAction;
use App\Filament\Shared\Resources\MunicipalityAdminUsers\Actions\MunicipalityAdminUserPendingInvitesAction;
use Filament\Resources\Pages\ListRecords;

class ListMunicipalityAdminUsers extends ListRecords
{
    protected static string $resource = MunicipalityAdminUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MunicipalityAdminUserPendingInvitesAction::make(),
            MunicipalityAdminUserInviteAction::make(),
        ];
    }
}
