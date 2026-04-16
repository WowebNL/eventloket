<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use Filament\Forms\Components\Radio;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\HtmlString;

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
                TextEntry::make('content7')
                    ->hiddenLabel()
                    ->state(new HtmlString('<p>Welkom bij de pagina voor het indienen van melding. Wij vragen u nu om verderde details voor uw evenement in te vullen.</p>'))
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('content7');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (false);
                    }),
                Radio::make('wordtErAlcoholGeschonkenTijdensUwEvenement')
                    ->label('Wordt er alcohol geschonken tijdens uw evenement?')
                    ->options([
                        'Ja' => 'Ja',
                        'Nee' => 'Nee',
                    ])
                    ->required()
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('wordtErAlcoholGeschonkenTijdensUwEvenement');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (false);
                    })
                    ->live(),
                TextEntry::make('content9')
                    ->hiddenLabel()
                    ->state(new HtmlString('<p>{% if gemeenteVariabelen.melding_alcohol_ontheffing_tekst %}</p><p>{{ gemeenteVariabelen.melding_alcohol_ontheffing_tekst|urlize }}</p><p>{% endif %}</p>'))
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('content9');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (! ($get('wordtErAlcoholGeschonkenTijdensUwEvenement') === 'Ja'));
                    }),
                Radio::make('wordenErFilmopnamesMetBehulpVanDronesGemaakt')
                    ->label('Worden er filmopnames met behulp van drones gemaakt? ')
                    ->options([
                        'Ja' => 'Ja',
                        'Nee' => 'Nee',
                    ])
                    ->required()
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('wordenErFilmopnamesMetBehulpVanDronesGemaakt');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (false);
                    })
                    ->live(),
                TextEntry::make('content10')
                    ->hiddenLabel()
                    ->state(new HtmlString('<p>{% if gemeenteVariabelen.melding_drone_ontheffing_tekst %}</p><p>{{ gemeenteVariabelen.melding_drone_ontheffing_tekst }}</p><p>{% endif %}</p>'))
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('content10');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (! ($get('wordenErFilmopnamesMetBehulpVanDronesGemaakt') === 'Ja'));
                    }),
                Radio::make('vindenErActiviteitenPlaatsWaarvoorMogelijkBrandveiligheidseisenGelden')
                    ->label('Vinden er activiteiten plaats, waarvoor mogelijk brandveiligheidseisen gelden?')
                    ->options([
                        'Ja' => 'Ja',
                        'Nee' => 'Nee',
                    ])
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('vindenErActiviteitenPlaatsWaarvoorMogelijkBrandveiligheidseisenGelden');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (false);
                    })
                    ->live(),
                TextEntry::make('content11')
                    ->hiddenLabel()
                    ->state(new HtmlString('<p>Raadpleeg <a href="https://www.brandweer.nl/onderwerpen/evenement-organiseren/" target="_blank" rel="noopener noreferrer">de website van de brandweer</a> voor de regelgeving en ontheffing evenement organiseren.</p>'))
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('content11');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (! ($get('vindenErActiviteitenPlaatsWaarvoorMogelijkBrandveiligheidseisenGelden') === 'Ja'));
                    }),
            ]);
    }
}
