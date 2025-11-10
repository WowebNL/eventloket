<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\Locations\Pages;

use App\Filament\Municipality\Clusters\Settings\Resources\Locations\LocationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLocations extends ListRecords
{
    protected static string $resource = LocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
