<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZaaktypeMappings\Pages;

use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZaaktypeMappings\MunicipalityZaaktypeMappingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMunicipalityZaaktypeMapping extends EditRecord
{
    protected static string $resource = MunicipalityZaaktypeMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return MunicipalityZaaktypeMappingResource::pruneEigenschapMap($data);
    }
}
