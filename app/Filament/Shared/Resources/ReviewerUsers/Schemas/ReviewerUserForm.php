<?php

namespace App\Filament\Shared\Resources\ReviewerUsers\Schemas;

use App\Enums\Role;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ReviewerUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('resources/reviewer_user.columns.name.label'))
                    ->required()
                    ->maxLength(255),
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
