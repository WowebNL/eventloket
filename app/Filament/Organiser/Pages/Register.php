<?php

namespace App\Filament\Organiser\Pages;

use App\Enums\Role;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Auth\Register as BaseRegister;

class Register extends BaseRegister
{
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                TextInput::make('phone')
                    ->label(__('organiser/pages/auth/register.form.phone.label'))
                    ->maxLength(255),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }

    protected function mutateFormDataBeforeRegister(array $data): array
    {
        $data['role'] = Role::Organiser;

        return $data;
    }
}
