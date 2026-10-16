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
            // Filament consults the resource's canCreate() only when the create
            // page itself is opened (CreateRecord::authorizeAccess aborts 403),
            // never to hide this button. Without this the button is painted for
            // a municipality that may not start a destruction here.
            CreateAction::make()
                ->visible(fn (): bool => DestructionListResource::canCreate()),
        ];
    }
}
