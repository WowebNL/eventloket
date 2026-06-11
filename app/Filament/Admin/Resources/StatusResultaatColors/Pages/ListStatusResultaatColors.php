<?php

namespace App\Filament\Admin\Resources\StatusResultaatColors\Pages;

use App\Filament\Admin\Resources\StatusResultaatColors\StatusResultaatColorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStatusResultaatColors extends ListRecords
{
    protected static string $resource = StatusResultaatColorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
