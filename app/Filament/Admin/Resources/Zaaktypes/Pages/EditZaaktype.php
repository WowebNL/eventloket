<?php

namespace App\Filament\Admin\Resources\Zaaktypes\Pages;

use App\Filament\Admin\Resources\Zaaktypes\ZaaktypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditZaaktype extends EditRecord
{
    protected static string $resource = ZaaktypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
