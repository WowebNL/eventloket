<?php

namespace App\Filament\Shared\Resources\Zaken\Pages;

use App\Filament\Shared\Resources\Zaken\ZaakResource;
use Filament\Resources\Pages\ViewRecord;

class ViewZaak extends ViewRecord
{
    protected static string $resource = ZaakResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
