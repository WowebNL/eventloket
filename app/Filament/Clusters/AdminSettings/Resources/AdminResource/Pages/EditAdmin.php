<?php

namespace App\Filament\Clusters\AdminSettings\Resources\AdminResource\Pages;

use App\Filament\Clusters\AdminSettings\Resources\AdminResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAdmin extends EditRecord
{
    protected static string $resource = AdminResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
