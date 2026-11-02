<?php

namespace App\Filament\Municipality\Clusters\Archiving\Resources\DestructionListResource\Pages;

use App\Filament\Municipality\Clusters\Archiving\Resources\DestructionListResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDestructionList extends ViewRecord
{
    protected static string $resource = DestructionListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...DestructionListResource::getWorkflowActions(),
            EditAction::make(),
        ];
    }
}
