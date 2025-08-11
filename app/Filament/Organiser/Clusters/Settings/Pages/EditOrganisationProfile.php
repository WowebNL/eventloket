<?php

namespace App\Filament\Organiser\Clusters\Settings\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\EditTenantProfile;

class EditOrganisationProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return __('organiser/pages/tenancy/profile.label');
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
                    ->disabled()
                    ->unique(ignoreRecord: true)
                    ->validationMessages([
                        'unique' => __('organiser/pages/tenancy/register.form.coc_number.validation.unique'),
                    ])
                    ->required()
                    ->length(8),
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
}
