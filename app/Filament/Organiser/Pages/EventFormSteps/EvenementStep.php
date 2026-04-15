<?php

namespace App\Filament\Organiser\Pages\EventFormSteps;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;

class EvenementStep
{
    public static function make(): Step
    {
        return Step::make('Het evenement')
            ->icon('heroicon-o-sparkles')
            ->schema([
                TextInput::make('naam_evenement')
                    ->label('Wat is de naam van het evenement?')
                    ->required()
                    ->live(onBlur: true),

                Textarea::make('omschrijving_evenement')
                    ->label(fn (Get $get) => 'Geef een korte omschrijving van het evenement '.($get('naam_evenement') ?? ''))
                    ->required()
                    ->rows(4)
                    ->visible(fn (Get $get) => filled($get('naam_evenement'))),

                Select::make('soort_evenement')
                    ->label(fn (Get $get) => 'Wat voor soort evenement is '.($get('naam_evenement') ?? 'uw evenement').'?')
                    ->options([
                        'buurtfeest' => 'Buurt-, barbecue of straatfeest, buitenspeeldagen, (eindejaars-)feesten, garagesales',
                        'muziekevenement' => 'Muziekevenement Cultuur- of kunstevenement of toneelvoorstellingen',
                        'sportevenement' => 'Sportevenement',
                        'markt' => 'Markt of braderie',
                        'circus' => 'Circus',
                        'kermis' => 'Kermis',
                        'beurs' => 'Beurs of Congres',
                        'auto_motor' => 'Auto- scooter- of motorshow',
                        'optocht' => 'Optocht',
                        'herdenking' => 'Herdenking of ceremonie',
                        'studentenevenement' => 'Studentenevenement',
                        'horeca' => 'Horeca evenement',
                        'dance' => 'Dance evenement',
                        'film' => 'Film opnames',
                        'vreugdevuur' => 'Vreugdevuur, paasvuur, kampvuur',
                        'overig' => 'Overig evenement',
                    ])
                    ->searchable()
                    ->required()
                    ->visible(fn (Get $get) => filled($get('naam_evenement'))),

                Textarea::make('omschrijving_soort')
                    ->label('Omschrijf het soort evenement')
                    ->required()
                    ->rows(3)
                    ->visible(fn (Get $get) => filled($get('soort_evenement'))),
            ]);
    }
}
