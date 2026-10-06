<?php

namespace App\Filament\Municipality\Clusters\Archiving\Resources\DestructionListResource\Pages;

use App\Filament\Municipality\Clusters\Archiving\Resources\DestructionListResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDestructionList extends CreateRecord
{
    protected static string $resource = DestructionListResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
