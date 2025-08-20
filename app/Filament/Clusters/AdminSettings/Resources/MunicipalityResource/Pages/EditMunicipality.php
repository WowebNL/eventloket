<?php

namespace App\Filament\Clusters\AdminSettings\Resources\MunicipalityResource\Pages;

use App\Filament\Clusters\AdminSettings\Resources\MunicipalityResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMunicipality extends EditRecord
{
    protected static string $resource = MunicipalityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
