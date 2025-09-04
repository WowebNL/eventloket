<?php

namespace App\Filament\Shared\Resources\MunicipalityAdminUsers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MunicipalityAdminUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('municipality/resources/municipality_admin.columns.name.label')),
            ]);
    }
}
