<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZgwConnections\Pages;

use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZgwConnections\MunicipalityZgwConnectionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMunicipalityZgwConnection extends EditRecord
{
    protected static string $resource = MunicipalityZgwConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Never surface the stored (encrypted) client secret in the form; leaving
     * the field blank keeps the existing secret on save.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        unset($data['client_secret']);

        return $data;
    }
}
