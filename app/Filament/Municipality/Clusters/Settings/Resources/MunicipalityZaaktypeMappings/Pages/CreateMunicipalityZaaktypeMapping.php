<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZaaktypeMappings\Pages;

use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZaaktypeMappings\MunicipalityZaaktypeMappingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMunicipalityZaaktypeMapping extends CreateRecord
{
    protected static string $resource = MunicipalityZaaktypeMappingResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return MunicipalityZaaktypeMappingResource::pruneEigenschapMap($data);
    }
}
