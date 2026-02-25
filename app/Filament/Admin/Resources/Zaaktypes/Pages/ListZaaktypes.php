<?php

namespace App\Filament\Admin\Resources\Zaaktypes\Pages;

use App\Filament\Admin\Resources\Zaaktypes\ZaaktypeResource;
use Filament\Resources\Pages\ListRecords;

class ListZaaktypes extends ListRecords
{
    protected static string $resource = ZaaktypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
