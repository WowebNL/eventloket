<?php

namespace App\Filament\Organiser\Pages\EventFormSteps;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Wizard\Step;

class ContactgegevensStep
{
    public static function make(): Step
    {
        return Step::make('Contactgegevens')
            ->icon('heroicon-o-user')
            ->schema([
                Fieldset::make('Uw gegevens')
                    ->schema([
                        TextInput::make('voornaam')
                            ->label('Wat is uw voornaam?')
                            ->required(),
                        TextInput::make('achternaam')
                            ->label('Wat is uw achternaam?')
                            ->required(),
                        TextInput::make('email')
                            ->label('Wat is uw e-mailadres?')
                            ->email()
                            ->required(),
                        TextInput::make('telefoon')
                            ->label('Wat is uw telefoonnummer?')
                            ->tel()
                            ->required(),
                    ])
                    ->columns(2),

                Fieldset::make('Organisatie')
                    ->schema([
                        TextInput::make('kvk')
                            ->label('KvK-nummer')
                            ->required(),
                        TextInput::make('organisatie_naam')
                            ->label('Naam organisatie')
                            ->required(),
                        TextInput::make('organisatie_email')
                            ->label('E-mailadres organisatie')
                            ->email(),
                        TextInput::make('organisatie_telefoon')
                            ->label('Telefoonnummer organisatie')
                            ->tel()
                            ->required(),
                    ])
                    ->columns(2),

                Fieldset::make('Vestigingsadres organisatie')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextInput::make('vestiging_postcode')
                                    ->label('Postcode')
                                    ->required(),
                                TextInput::make('vestiging_huisnummer')
                                    ->label('Huisnummer')
                                    ->required(),
                                TextInput::make('vestiging_huisletter')
                                    ->label('Huisletter'),
                                TextInput::make('vestiging_toevoeging')
                                    ->label('Toevoeging'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('vestiging_straat')
                                    ->label('Straatnaam')
                                    ->required(),
                                TextInput::make('vestiging_plaats')
                                    ->label('Plaatsnaam')
                                    ->required(),
                            ]),
                    ]),

                Fieldset::make('Correspondentieadres')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextInput::make('correspondentie_postcode')
                                    ->label('Postcode')
                                    ->required(),
                                TextInput::make('correspondentie_huisnummer')
                                    ->label('Huisnummer')
                                    ->required(),
                                TextInput::make('correspondentie_huisletter')
                                    ->label('Huisletter'),
                                TextInput::make('correspondentie_toevoeging')
                                    ->label('Toevoeging'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('correspondentie_straat')
                                    ->label('Straatnaam')
                                    ->required(),
                                TextInput::make('correspondentie_plaats')
                                    ->label('Plaatsnaam')
                                    ->required(),
                            ]),
                    ]),

                Repeater::make('contactpersonen')
                    ->label('Extra contactpersonen')
                    ->schema([
                        TextInput::make('rol')
                            ->label('Rol')
                            ->placeholder('bijv. Voorafgaand, Tijdens, Na')
                            ->required(),
                        TextInput::make('naam')
                            ->label('Naam')
                            ->required(),
                        TextInput::make('telefoon')
                            ->label('Telefoonnummer')
                            ->tel()
                            ->required(),
                        TextInput::make('email')
                            ->label('E-mailadres')
                            ->email()
                            ->required(),
                    ])
                    ->columns(4)
                    ->defaultItems(0)
                    ->maxItems(5)
                    ->addActionLabel('Contactpersoon toevoegen'),
            ]);
    }
}
