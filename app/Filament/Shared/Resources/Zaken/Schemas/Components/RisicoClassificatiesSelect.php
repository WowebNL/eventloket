<?php

namespace App\Filament\Shared\Resources\Zaken\Schemas\Components;

use Filament\Forms\Components\Select;

class RisicoClassificatiesSelect
{
    public static function make(): Select
    {
        return Select::make('risico_classificaties')
            ->label(__('resources/zaak.columns.risico_classificatie.label'))
            ->options([
                '0' => '0',
                'A' => 'A',
                'B' => 'B',
                'C' => 'C',
            ])
            ->multiple();
    }
}
