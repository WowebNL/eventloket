<?php

namespace App\Filament\Admin\Resources\StatusResultaatColors\Pages;

use App\Filament\Admin\Resources\StatusResultaatColors\StatusResultaatColorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStatusResultaatColor extends EditRecord
{
    protected static string $resource = StatusResultaatColorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
