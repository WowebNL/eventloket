<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\Locations\Pages;

use App\Filament\Municipality\Clusters\Settings\Resources\Locations\LocationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLocation extends EditRecord
{
    protected static string $resource = LocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
