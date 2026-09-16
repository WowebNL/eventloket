<?php

namespace App\Filament\Municipality\Clusters\Archiving\Resources\DestructionListResource\Pages;

use App\Filament\Municipality\Clusters\Archiving\Resources\DestructionListResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDestructionLists extends ListRecords
{
    protected static string $resource = DestructionListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
