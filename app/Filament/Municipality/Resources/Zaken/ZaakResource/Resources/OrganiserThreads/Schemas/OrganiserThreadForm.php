<?php

namespace App\Filament\Municipality\Resources\Zaken\ZaakResource\Resources\OrganiserThreads\Schemas;

use App\Enums\ThreadType;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class OrganiserThreadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->options(ThreadType::class)
                    ->required(),
            ]);
    }
}
