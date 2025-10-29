<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityVariables\Pages;

use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityVariables\MunicipalityVariableResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMunicipalityVariables extends ListRecords
{
    protected static string $resource = MunicipalityVariableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
