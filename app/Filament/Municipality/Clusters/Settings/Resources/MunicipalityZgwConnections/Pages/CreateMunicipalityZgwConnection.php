<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZgwConnections\Pages;

use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZgwConnections\MunicipalityZgwConnectionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMunicipalityZgwConnection extends CreateRecord
{
    protected static string $resource = MunicipalityZgwConnectionResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return MunicipalityZgwConnectionResource::pruneVertrouwelijkheidMap($data);
    }
}
