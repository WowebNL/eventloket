<?php

namespace App\Filament\Resources\Organisations\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OrganisationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('admin/resources/organisation.form.name.label'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('coc_number')
                    ->label(__('admin/resources/organisation.form.coc_number.label'))
                    ->unique()
                    ->validationMessages([
                        'unique' => __('admin/resources/organisation.form.coc_number.validation.unique'),
                    ])
                    ->required()
                    ->length(8),
                TextInput::make('address')
                    ->label(__('admin/resources/organisation.form.address.label'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('bag_id')
                    ->label(__('admin/resources/organisation.form.bag_id.label'))
                    ->helperText(__('admin/resources/organisation.form.bag_id.helper_text'))
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('admin/resources/organisation.form.email.label'))
                    ->email()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label(__('admin/resources/organisation.form.phone.label'))
                    ->maxLength(20),
            ]);
    }
}
