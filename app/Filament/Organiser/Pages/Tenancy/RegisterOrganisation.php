<?php

namespace App\Filament\Organiser\Pages\Tenancy;

use App\Enums\OrganisationRole;
use App\Models\Organisation;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\RegisterTenant;

class RegisterOrganisation extends RegisterTenant
{
    public static function getLabel(): string
    {
        return __('organiser/pages/tenancy/register.label');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label(__('organiser/pages/tenancy/register.form.name.label'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('coc_number')
                    ->label(__('organiser/pages/tenancy/register.form.coc_number.label'))
                    ->unique()
                    ->validationMessages([
                        'unique' => __('organiser/pages/tenancy/register.form.coc_number.validation.unique'),
                    ])
                    ->required()
                    ->maxLength(8),
                TextInput::make('address')
                    ->label(__('organiser/pages/tenancy/register.form.address.label'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('organiser/pages/tenancy/register.form.email.label'))
                    ->email()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label(__('organiser/pages/tenancy/register.form.phone.label'))
                    ->maxLength(255),
            ]);
    }

    protected function handleRegistration(array $data): Organisation
    {
        $organisation = Organisation::create($data);

        $organisation->users()->attach(auth()->user(), [
            'role' => OrganisationRole::Owner,
        ]);

        return $organisation;
    }
}
