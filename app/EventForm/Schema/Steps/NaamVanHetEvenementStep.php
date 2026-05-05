<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use App\EventForm\Template\LabelRenderer;
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
    public const UUID = 'c3c17c65-0cf1-4a79-a348-75eab01f46ec';

    public static function make(): Step
    {
        return Step::make('Het evenement')
            ->key(self::UUID)
            ->schema([
                TextInput::make('watIsDeNaamVanHetEvenementVergunning')
                    ->label('Wat is de naam van het evenement?')
                    ->required()
                    ->maxLength(1000)
                    ->live(),
                Textarea::make('geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning')
                    ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Geef een korte omschrijving van het evenement {{ watIsDeNaamVanHetEvenementVergunning }}', $livewire->state()))
                    ->required()
                    ->maxLength(10000)
                    ->hidden(function (Get $get, $livewire): bool {
                        $rule = $livewire->state()->isFieldHidden('geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning');
                        if ($rule !== null) {
                            return $rule;
                        }

                        return $get('watIsDeNaamVanHetEvenementVergunning') === null || $get('watIsDeNaamVanHetEvenementVergunning') === '';
                    }),
                Select::make('soortEvenement')
                    ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Wat voor soort evenement is {{ watIsDeNaamVanHetEvenementVergunning }}?', $livewire->state()))
                    ->options([
                        'Buurt-, barbecue of straatfeest, buitenspeeldagen, (eindejaars-)feesten, garagesales' => 'Buurt-, barbecue of straatfeest, buitenspeeldagen, (eindejaars-)feesten, garagesales',
                        'Muziekevenement Cultuur- of kunstevenement of toneelvoorstellingen' => 'Muziekevenement Cultuur- of kunstevenement of toneelvoorstellingen',
                        'Sportevenement' => 'Sportevenement',
                        'Markt of braderie' => 'Markt of braderie',
                        'Circus' => 'Circus',
                        'Kermis' => 'Kermis',
                        'Beurs of Congres' => 'Beurs of Congres',
                        'Auto- scooter- of motorshow' => 'Auto- scooter- of motorshow',
                        'Vliegshow' => 'Vliegshow',
                        'Festival' => 'Festival',
                        'Optocht, processie of corso' => 'Optocht, processie of corso',
                        'Culinair evenement' => 'Culinair evenement',
                        'Dierenshow' => 'Dierenshow',
                        'Evenement op het water' => 'Evenement op het water',
                        'Scoutingwedstrijden' => 'Scoutingwedstrijden',
                        'Truck event' => 'Truck event',
                        'Verkeerseducatie' => 'Verkeerseducatie',
                        'Halloweenfeesten' => 'Halloweenfeesten',
                        'Anders' => 'Anders',
                    ])
                    ->required()
                    ->hidden(function (Get $get, $livewire): bool {
                        $rule = $livewire->state()->isFieldHidden('soortEvenement');
                        if ($rule !== null) {
                            return $rule;
                        }

                        return $get('watIsDeNaamVanHetEvenementVergunning') === null || $get('watIsDeNaamVanHetEvenementVergunning') === '';
                    })
                    ->live(),
                Textarea::make('omschrijfHetSoortEvenement')
                    ->label('Omschrijf het soort evenement')
                    ->required()
                    ->maxLength(10000)
                    ->hidden(function (Get $get, $livewire): bool {
                        $rule = $livewire->state()->isFieldHidden('omschrijfHetSoortEvenement');
                        if ($rule !== null) {
                            return $rule;
                        }

                        return ! ($get('soortEvenement') === 'Anders');
                    }),
                Radio::make('gaatHetHierOmEenPeriodiekTerugkerendeMarktJaarmarktOfWeekmarktWaarvoorDeGemeenteEenBesluitHeeftGenomenMetBetrekkingTotDeMarktdagen')
                    ->label('Gaat het hier om een periodiek terugkerende markt (jaarmarkt of weekmarkt), waarvoor de gemeente een besluit heeft genomen met betrekking tot de marktdagen?')
                    ->options([
                        'Ja' => 'Ja',
                        'Nee' => 'Nee',
                    ])
                    ->hidden(function (Get $get, $livewire): bool {
                        $rule = $livewire->state()->isFieldHidden('gaatHetHierOmEenPeriodiekTerugkerendeMarktJaarmarktOfWeekmarktWaarvoorDeGemeenteEenBesluitHeeftGenomenMetBetrekkingTotDeMarktdagen');
                        if ($rule !== null) {
                            return $rule;
                        }

                        return ! ($get('soortEvenement') === 'Markt of braderie');
                    }),
            ]);
    }
}
