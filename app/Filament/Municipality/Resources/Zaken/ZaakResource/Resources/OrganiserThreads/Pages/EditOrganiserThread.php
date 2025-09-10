<?php

namespace App\Filament\Municipality\Resources\Zaken\ZaakResource\Resources\OrganiserThreads\Pages;

use App\Filament\Municipality\Resources\Zaken\ZaakResource\Resources\OrganiserThreads\OrganiserThreadResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOrganiserThread extends EditRecord
{
    protected static string $resource = OrganiserThreadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
