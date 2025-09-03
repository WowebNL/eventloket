<?php

namespace App\Filament\Municipality\Resources\Zaken\Schemas;

use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ZaakInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Flex::make([
                    Section::make('Informatie')
                        ->schema([
                            // Add your section components here
                        ]),
                    Section::make('Acties')
                        ->schema([
                            // Add your section components here
                        ])->grow(false),
                ])->from('md'),
            ]);
    }
}
