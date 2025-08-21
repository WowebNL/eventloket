<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityAdminUserResource\Pages;

use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityAdminUserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMunicipalityAdminUser extends EditRecord
{
    protected static string $resource = MunicipalityAdminUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
