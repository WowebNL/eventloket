<?php

namespace App\Filament\Admin\Resources\MunicipalityVariables\Pages;

use App\Filament\Admin\Resources\MunicipalityVariables\MunicipalityVariableResource;
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
