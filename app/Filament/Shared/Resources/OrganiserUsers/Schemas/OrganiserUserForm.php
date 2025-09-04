<?php

namespace App\Filament\Shared\Resources\OrganiserUsers\Schemas;

use App\Enums\OrganisationRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OrganiserUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('admin/resources/organisation.user.form.name.label'))
                    ->required(),
                TextInput::make('email')
                    ->label(__('admin/resources/organisation.user.form.email.label'))
                    ->email()
                    ->required(),
                TextInput::make('phone')
                    ->label(__('admin/resources/organisation.user.form.phone.label'))
                    ->maxLength(20),
                Select::make('role')
                    ->label(__('admin/resources/organisation.user.form.role.label'))
                    ->options(OrganisationRole::class)
                    ->required(),
            ]);
    }
}
