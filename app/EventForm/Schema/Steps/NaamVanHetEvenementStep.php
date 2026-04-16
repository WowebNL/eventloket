<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;

/**
 * @openforms-step-uuid c3c17c65-0cf1-4a79-a348-75eab01f46ec
 *
 * @openforms-step-index 1
 */
final class NaamVanHetEvenementStep
{
    public static function make(): Step
    {
        return Step::make('Het evenement')
            ->schema([
                TextInput::make('watIsDeNaamVanHetEvenementVergunning')
                    ->label('Wat is de naam van het evenement?')
                    ->required()
                    ->maxLength(1000),
                Textarea::make('geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning')
                    ->label('Geef een korte omschrijving van het evenement {{ watIsDeNaamVanHetEvenementVergunning }}')
                    ->required()
                    ->maxLength(10000)
                    ->hidden(fn (Get $get): bool => $get('watIsDeNaamVanHetEvenementVergunning') === ''),
                Select::make('soortEvenement')
                    ->label('Wat voor soort evenement is {{ watIsDeNaamVanHetEvenementVergunning }}?')
                    ->required()
                    ->hidden(fn (Get $get): bool => $get('watIsDeNaamVanHetEvenementVergunning') === ''),
                Textarea::make('omschrijfHetSoortEvenement')
                    ->label('Omschrijf het soort evenement')
                    ->required()
                    ->maxLength(10000)
                    ->visible(fn (Get $get): bool => $get('soortEvenement') === 'Anders'),
                Radio::make('gaatHetHierOmEenPeriodiekTerugkerendeMarktJaarmarktOfWeekmarktWaarvoorDeGemeenteEenBesluitHeeftGenomenMetBetrekkingTotDeMarktdagen')
                    ->label('Gaat het hier om een periodiek terugkerende markt (jaarmarkt of weekmarkt), waarvoor de gemeente een besluit heeft genomen met betrekking tot de marktdagen?')
                    ->visible(fn (Get $get): bool => $get('soortEvenement') === 'Markt of braderie'),
            ]);
    }
}
