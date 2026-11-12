<?php

namespace App\Filament\Municipality\Clusters\Archiving\Resources\DestructionListResource\Pages;

use App\Filament\Municipality\Clusters\Archiving\Resources\DestructionListResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDestructionList extends EditRecord
{
    protected static string $resource = DestructionListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...DestructionListResource::getWorkflowActions(),
            DeleteAction::make(),
        ];
    }
}
