<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZaaktypeMappings\Pages;

use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZaaktypeMappings\MunicipalityZaaktypeMappingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMunicipalityZaaktypeMappings extends ListRecords
{
    protected static string $resource = MunicipalityZaaktypeMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
