<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use App\EventForm\Components\InfoText;
use App\EventForm\Components\JaNeeOptions;
use App\EventForm\State\FormState;
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
                    ->options(JaNeeOptions::OPTIONS)
                    ->required()
                    ->live(),
                InfoText::info('content9', fn (FormState $state) => self::renderOntheffingTekst(
                    $state->get('gemeenteVariabelen.melding_alcohol_ontheffing_tekst'),
                ))
                    ->hidden(function (Get $get, $livewire): bool {
                        $rule = $livewire->state()->isFieldHidden('content9');
                        if ($rule !== null) {
                            return $rule;
                        }

                        return ! ($get('wordtErAlcoholGeschonkenTijdensUwEvenement') === 'Ja');
                    }),
                Radio::make('wordenErFilmopnamesMetBehulpVanDronesGemaakt')
                    ->label('Worden er filmopnames met behulp van drones gemaakt? ')
                    ->options(JaNeeOptions::OPTIONS)
                    ->required()
                    ->live(),
                InfoText::info('content10', fn (FormState $state) => self::renderOntheffingTekst(
                    $state->get('gemeenteVariabelen.melding_drone_ontheffing_tekst'),
                ))
                    ->hidden(function (Get $get, $livewire): bool {
                        $rule = $livewire->state()->isFieldHidden('content10');
                        if ($rule !== null) {
                            return $rule;
                        }

                        return ! ($get('wordenErFilmopnamesMetBehulpVanDronesGemaakt') === 'Ja');
                    }),
                Radio::make('vindenErActiviteitenPlaatsWaarvoorMogelijkBrandveiligheidseisenGelden')
                    ->label('Vinden er activiteiten plaats, waarvoor mogelijk brandveiligheidseisen gelden?')
                    ->options(JaNeeOptions::OPTIONS)
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

    /**
     * Toon de gemeente-specifieke ontheffings-tekst (alcohol of drones)
     * uit `gemeenteVariabelen` zodra die ingevuld is. Lege/null waarde →
     * lege string zodat de alert-wrapper niet rondom een leeg blok komt.
     * De tekst is municipality-config (geen user-input rechtstreeks),
     * maar `e()` is gratis en dekt 't toch af.
     */
    private static function renderOntheffingTekst(mixed $tekst): string
    {
        if (! is_string($tekst) || trim($tekst) === '') {
            return '';
        }

        return '<p>'.e($tekst).'</p>';
    }
}
