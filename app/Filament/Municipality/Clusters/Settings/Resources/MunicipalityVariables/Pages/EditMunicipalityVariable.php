<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityVariables\Pages;

use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityVariables\MunicipalityVariableResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMunicipalityVariable extends EditRecord
{
    protected static string $resource = MunicipalityVariableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
