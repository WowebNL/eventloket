<?php

namespace App\Filament\Municipality\Resources\Zaken\Pages;

use App\Filament\Municipality\Resources\Zaken\ZaakResource;
use Filament\Resources\Pages\ListRecords;

class ListZaken extends ListRecords
{
    protected static string $resource = ZaakResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
