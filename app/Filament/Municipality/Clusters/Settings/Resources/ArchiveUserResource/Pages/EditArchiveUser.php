<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\ArchiveUserResource\Pages;

use App\Filament\Municipality\Clusters\Settings\Resources\ArchiveUserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditArchiveUser extends EditRecord
{
    protected static string $resource = ArchiveUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
