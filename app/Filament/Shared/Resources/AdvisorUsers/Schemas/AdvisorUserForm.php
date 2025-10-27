<?php

namespace App\Filament\Shared\Resources\AdvisorUsers\Schemas;

use App\Enums\AdvisoryRole;
use App\Filament\Shared\Pages\EditProfile;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AdvisorUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \App\Filament\Organiser\Pages\EditProfile::getFirstNameFormComponent(),
                EditProfile::getLastNameFormComponent(),
                TextInput::make('email')
                    ->label(__('resources/advisor_user.form.email.label'))
                    ->disabled(),
                TextInput::make('phone')
                    ->label(__('resources/advisor_user.form.phone.label'))
                    ->maxLength(20),
                Select::make('pivot.role')
                    ->label(__('resources/advisor_user.form.role.label'))
                    ->options(AdvisoryRole::class)
                    ->required(),
            ]);
    }
}
