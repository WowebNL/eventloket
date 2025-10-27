<?php

namespace App\Filament\Organiser\Pages;

use App\Enums\Role;
use App\Models\Users\OrganiserUser;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class Register extends \Filament\Auth\Pages\Register
{
    protected string $userModel = OrganiserUser::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                EditProfile::getFirstNameFormComponent(),
                EditProfile::getLastNameFormComponent(),
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

    protected function mutateFormDataBeforeRegister(array $data): array
    {
        $data['role'] = Role::Organiser;
        $data['name'] = $data['first_name'].' '.$data['last_name'];

        return $data;
    }
}
