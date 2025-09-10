<?php

namespace App\Filament\Municipality\Resources\Zaken\ZaakResource\Resources\OrganiserThreads\Pages;

use App\Filament\Municipality\Resources\Zaken\ZaakResource\Resources\OrganiserThreads\OrganiserThreadResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOrganiserThread extends ViewRecord
{
    protected static string $resource = OrganiserThreadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
