<?php

namespace App\Filament\Shared\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

class EditProfile extends \Filament\Auth\Pages\EditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFirstNameFormComponent(),
                $this->getLastNameFormComponent(),
                $this->getEmailFormComponent(),
                /** @phpstan-ignore-next-line */
                $this->getPasswordFormComponent()->helperText(app()->isProduction() ? __('organiser/pages/auth/register.form.password.helper_text') : null),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }

    public static function getFirstNameFormComponent(): Component
    {
        return TextInput::make('first_name')
            ->label(__('shared/pages/edit-profile.form.first_name.label'))
            ->required()
            ->maxLength(255)
            ->autofocus();
    }

    public static function getLastNameFormComponent(): Component
    {
        return TextInput::make('last_name')
            ->label(__('shared/pages/edit-profile.form.last_name.label'))
            ->required()
            ->maxLength(255);
    }
}
