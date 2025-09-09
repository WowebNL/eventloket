<?php

namespace App\Filament\Shared\Resources\MunicipalityAdminUsers\Schemas;

use App\Enums\Role;
use Filament\Forms\Components\Select;
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
                Select::make('role')
                    ->label(__('municipality/resources/municipality_admin.columns.role.label'))
                    ->options([
                        Role::Reviewer->value => Role::Reviewer->getLabel(),
                        Role::ReviewerMunicipalityAdmin->value => Role::ReviewerMunicipalityAdmin->getLabel(),
                        Role::MunicipalityAdmin->value => Role::MunicipalityAdmin->getLabel(),
                    ])
                    ->selectablePlaceholder(false)
                    ->required(),
            ]);
    }
}
