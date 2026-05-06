<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use App\EventForm\Components\InfoText;
use Filament\Forms\Components\Radio;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;

/**
 * @openforms-step-uuid 5f986f16-6a3a-4066-9383-d71f09877f47
 *
 * @openforms-step-index 6
 */
final class MeldingStep
{
    public const UUID = '5f986f16-6a3a-4066-9383-d71f09877f47';

    public static function make(): Step
    {
        return Step::make('Melding')
            ->key(self::UUID)
            ->schema([
                InfoText::info('content7', '<p>Welkom bij de pagina voor het indienen van melding. Wij vragen u nu om verderde details voor uw evenement in te vullen.</p>'),
                Radio::make('wordtErAlcoholGeschonkenTijdensUwEvenement')
                    ->label('Wordt er alcohol geschonken tijdens uw evenement?')
                    ->options([
                        'Ja' => 'Ja',
                        'Nee' => 'Nee',
                    ])
                    ->required()
                    ->live(),
                InfoText::info('content9', '<p>{% if gemeenteVariabelen.melding_alcohol_ontheffing_tekst %}</p><p>{{ gemeenteVariabelen.melding_alcohol_ontheffing_tekst|urlize }}</p><p>{% endif %}</p>')
                    ->hidden(function (Get $get, $livewire): bool {
                        $rule = $livewire->state()->isFieldHidden('content9');
                        if ($rule !== null) {
                            return $rule;
                        }

                        return ! ($get('wordtErAlcoholGeschonkenTijdensUwEvenement') === 'Ja');
                    }),
                Radio::make('wordenErFilmopnamesMetBehulpVanDronesGemaakt')
                    ->label('Worden er filmopnames met behulp van drones gemaakt? ')
                    ->options([
                        'Ja' => 'Ja',
                        'Nee' => 'Nee',
                    ])
                    ->required()
                    ->live(),
                InfoText::info('content10', '<p>{% if gemeenteVariabelen.melding_drone_ontheffing_tekst %}</p><p>{{ gemeenteVariabelen.melding_drone_ontheffing_tekst }}</p><p>{% endif %}</p>')
                    ->hidden(function (Get $get, $livewire): bool {
                        $rule = $livewire->state()->isFieldHidden('content10');
                        if ($rule !== null) {
                            return $rule;
                        }

                        return ! ($get('wordenErFilmopnamesMetBehulpVanDronesGemaakt') === 'Ja');
                    }),
                Radio::make('vindenErActiviteitenPlaatsWaarvoorMogelijkBrandveiligheidseisenGelden')
                    ->label('Vinden er activiteiten plaats, waarvoor mogelijk brandveiligheidseisen gelden?')
                    ->options([
                        'Ja' => 'Ja',
                        'Nee' => 'Nee',
                    ])
                    ->live(),
                InfoText::info('content11', '<p>Raadpleeg <a href="https://www.brandweer.nl/onderwerpen/evenement-organiseren/" target="_blank" rel="noopener noreferrer">de website van de brandweer</a> voor de regelgeving en ontheffing evenement organiseren.</p>')
                    ->hidden(function (Get $get, $livewire): bool {
                        $rule = $livewire->state()->isFieldHidden('content11');
                        if ($rule !== null) {
                            return $rule;
                        }

                        return ! ($get('vindenErActiviteitenPlaatsWaarvoorMogelijkBrandveiligheidseisenGelden') === 'Ja');
                    }),
            ]);
    }
}
