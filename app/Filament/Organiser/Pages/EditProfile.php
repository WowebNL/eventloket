<?php

namespace App\Filament\Organiser\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EditProfile extends \App\Filament\Shared\Pages\EditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFirstNameFormComponent(),
                $this->getLastNameFormComponent(),
                $this->getEmailFormComponent(),
                TextInput::make('phone')
                    ->label(__('organiser/pages/auth/register.form.phone.label'))
                    ->maxLength(20)
                    ->required(),
                /** @phpstan-ignore-next-line */
                $this->getPasswordFormComponent()->helperText(app()->isProduction() ? __('organiser/pages/auth/register.form.password.helper_text') : null),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }
}
