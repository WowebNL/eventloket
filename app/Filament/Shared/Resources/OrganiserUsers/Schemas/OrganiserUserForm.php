<?php

namespace App\Filament\Shared\Resources\OrganiserUsers\Schemas;

use App\Enums\OrganisationRole;
use App\Filament\Shared\Pages\EditProfile;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OrganiserUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                EditProfile::getFirstNameFormComponent(),
                EditProfile::getLastNameFormComponent(),
                TextInput::make('email')
                    ->label(__('admin/resources/organisation.user.form.email.label'))
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique('users', 'email'),
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
