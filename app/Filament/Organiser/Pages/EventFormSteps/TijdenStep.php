<?php

namespace App\Filament\Organiser\Pages\EventFormSteps;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;

class TijdenStep
{
    public static function make(): Step
    {
        return Step::make('Tijden')
            ->icon('heroicon-o-clock')
            ->schema([
                Fieldset::make('Evenement')
                    ->schema([
                        DateTimePicker::make('evenement_start')
                            ->label('Start datum en tijdstip van uw evenement')
                            ->required()
                            ->native(false)
                            ->displayFormat('d-m-Y H:i'),
                        DateTimePicker::make('evenement_eind')
                            ->label('Eind datum en tijdstip van uw evenement')
                            ->required()
                            ->native(false)
                            ->displayFormat('d-m-Y H:i'),
                    ])
                    ->columns(2),

                Radio::make('heeft_opbouw')
                    ->label('Zijn er voorafgaand aan het evenement opbouwactiviteiten?')
                    ->options(['Ja' => 'Ja', 'Nee' => 'Nee'])
                    ->required()
                    ->live(),

                Fieldset::make('Opbouw')
                    ->schema([
                        DateTimePicker::make('opbouw_start')
                            ->label('Start datum en tijd van de opbouw')
                            ->required()
                            ->native(false)
                            ->displayFormat('d-m-Y H:i'),
                        DateTimePicker::make('opbouw_eind')
                            ->label('Eind datum en tijd van de opbouw')
                            ->required()
                            ->native(false)
                            ->displayFormat('d-m-Y H:i'),
                    ])
                    ->columns(2)
                    ->visible(fn (Get $get) => $get('heeft_opbouw') === 'Ja'),

                Radio::make('heeft_opbouw_tijdens')
                    ->label('Zijn er tijdens het evenement opbouwactiviteiten?')
                    ->options(['Ja' => 'Ja', 'Nee' => 'Nee'])
                    ->required(),

                Radio::make('heeft_afbouw')
                    ->label('Zijn er aansluitend aan het evenement afbouwactiviteiten?')
                    ->options(['Ja' => 'Ja', 'Nee' => 'Nee'])
                    ->required()
                    ->live(),

                Fieldset::make('Afbouw')
                    ->schema([
                        DateTimePicker::make('afbouw_start')
                            ->label('Start datum en tijdstip van de afbouw')
                            ->required()
                            ->native(false)
                            ->displayFormat('d-m-Y H:i'),
                        DateTimePicker::make('afbouw_eind')
                            ->label('Eind datum en tijdstip van de afbouw')
                            ->required()
                            ->native(false)
                            ->displayFormat('d-m-Y H:i'),
                    ])
                    ->columns(2)
                    ->visible(fn (Get $get) => $get('heeft_afbouw') === 'Ja'),

                Radio::make('heeft_afbouw_tijdens')
                    ->label('Zijn er tijdens het evenement afbouwactiviteiten?')
                    ->options(['Ja' => 'Ja', 'Nee' => 'Nee'])
                    ->required(),
            ]);
    }
}
