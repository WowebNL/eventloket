<?php

namespace App\Filament\Organiser\Pages\Tenancy;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\EditTenantProfile;

class EditOrganisationProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Organisation profile';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name'),
            ]);
    }
}
