<?php

namespace App\Filament\Clusters\AdminSettings\Resources\AdminUserResource\Pages;

use App\Filament\Clusters\AdminSettings\Resources\AdminUserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAdminUser extends EditRecord
{
    protected static string $resource = AdminUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
