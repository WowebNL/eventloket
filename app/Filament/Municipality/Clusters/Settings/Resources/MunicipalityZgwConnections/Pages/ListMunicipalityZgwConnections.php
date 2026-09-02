<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZgwConnections\Pages;

use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZgwConnections\MunicipalityZgwConnectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMunicipalityZgwConnections extends ListRecords
{
    protected static string $resource = MunicipalityZgwConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // One connection per municipality: only offer create when none exists.
            CreateAction::make()
                ->visible(fn (): bool => ! MunicipalityZgwConnectionResource::getEloquentQuery()->exists()),
        ];
    }
}
