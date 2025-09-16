<?php

namespace App\Filament\Organiser\Resources\Zaken\Pages;

use App\Filament\Organiser\Resources\Zaken\ZaakResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListZaken extends ListRecords
{
    protected static string $resource = ZaakResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }
}
