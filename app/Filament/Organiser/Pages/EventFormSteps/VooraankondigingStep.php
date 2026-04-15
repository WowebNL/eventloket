<?php

namespace App\Filament\Organiser\Pages\EventFormSteps;

use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Wizard\Step;

class VooraankondigingStep
{
    public static function make(): Step
    {
        return Step::make('Vooraankondiging')
            ->icon('heroicon-o-megaphone')
            ->schema([
                Radio::make('doel_eventloket')
                    ->label('Waarvoor wilt u Eventloket gebruiken?')
                    ->options([
                        'evenement' => 'Ik wil een evenement melden of een vergunning aanvragen',
                        'vooraankondiging' => 'Ik wil alleen een vooraankondiging doen',
                    ])
                    ->required()
                    ->live(),

                TextInput::make('aantal_aanwezigen')
                    ->label('Aantal verwachte aanwezigen')
                    ->numeric()
                    ->required()
                    ->minValue(1),
            ]);
    }
}
