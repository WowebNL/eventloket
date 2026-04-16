<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use Filament\Forms\Components\Radio;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\HtmlString;

/**
 * @openforms-step-uuid c75cc256-6729-4684-9f9b-ede6265b3e72
 *
 * @openforms-step-index 7
 */
final class RisicoscanStep
{
    public const UUID = 'c75cc256-6729-4684-9f9b-ede6265b3e72';

    public static function make(): Step
    {
        return Step::make('Risicoscan')
            ->key(self::UUID)
            ->schema([
                TextEntry::make('content')
                    ->hiddenLabel()
                    ->state(new HtmlString('<p>We stellen u nu een aantal standaard-vragen om een inschatting te maken in welke risico-categorie je evenement valt. Dit kan A-laag, B-middelmatig of C-hoog zijn. De risico-categorie is een indicator voor de hulpdiensten Politie, Brandweer en GHOR om hun inzet te bepalen.</p>'))
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('content');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (false);
                    }),
                Radio::make('watIsDeAantrekkingskrachtVanHetEvenement')
                    ->label('Wat is de aantrekkingskracht van het evenement?')
                    ->options([
                        '0.5' => 'Wijk of buurt',
                        '1' => 'Dorp',
                        '1.5' => 'Gemeentelijk',
                        '2' => 'Regionaal',
                        '2.5' => 'Nationaal',
                        '3' => 'Internationaal',
                    ])
                    ->required()
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('watIsDeAantrekkingskrachtVanHetEvenement');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (false);
                    }),
                Radio::make('watIsDeBelangrijksteLeeftijdscategorieVanDeDoelgroep')
                    ->label('Wat is de belangrijkste leeftijdscategorie van de doelgroep?')
                    ->options([
                        '0.25' => '0-15 jaar / met begeleiding',
                        '0.5' => '0-15 jaar / zonder begeleiding',
                        '0.75' => '15-18 jaar',
                        '0.5__2' => '18-30 jaar',
                        '0.25__2' => '30-70 jaar',
                        '1' => '70+ jaar',
                        '0.75__2' => 'Alle leeftijden',
                    ])
                    ->required()
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('watIsDeBelangrijksteLeeftijdscategorieVanDeDoelgroep');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (false);
                    }),
                Radio::make('isErSprakeVanZanwezigheidVanPolitiekeAandachtEnOfMediageniekheid')
                    ->label('Is er sprake van aanwezigheid van politieke aandacht en/of mediageniekheid?')
                    ->options([
                        '0' => 'Nee',
                        '1' => 'Ja',
                    ])
                    ->required()
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('isErSprakeVanZanwezigheidVanPolitiekeAandachtEnOfMediageniekheid');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (false);
                    }),
                Radio::make('isEenDeelVanDeDoelgroepVerminderdZelfredzaam')
                    ->label('Is een deel van de doelgroep verminderd zelfredzaam?')
                    ->options([
                        '1' => 'Niet zelfredzaam',
                        '0.5' => 'Beperkt zelfredzaam',
                        '0.25' => 'Voldoende zelfredzaam',
                        '0' => 'Volledig zelfredzaam',
                    ])
                    ->required()
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('isEenDeelVanDeDoelgroepVerminderdZelfredzaam');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (false);
                    }),
                Radio::make('isErSprakeVanAanwezigheidVanRisicovolleActiviteiten')
                    ->label('Is er sprake van aanwezigheid van risicovolle activiteiten?')
                    ->options([
                        '0' => 'Nee',
                        '1' => 'Ja',
                    ])
                    ->required()
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('isErSprakeVanAanwezigheidVanRisicovolleActiviteiten');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (false);
                    }),
                Radio::make('watIsHetGrootsteDeelVanDeSamenstellingVanDeDoelgroep')
                    ->label('Wat is het grootste deel van de samenstelling van de doelgroep?')
                    ->options([
                        '0.5' => 'Alleen toeschouwers',
                        '0.75' => 'Combinatie toeschouwers en deelnemers',
                        '1' => 'Alleen deelnemers',
                    ])
                    ->required()
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('watIsHetGrootsteDeelVanDeSamenstellingVanDeDoelgroep');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (false);
                    }),
                Radio::make('isErSprakeVanOvernachten')
                    ->label('Is er sprake van overnachten?')
                    ->options([
                        '0' => 'Er wordt niet overnacht of er wordt overnacht op een daartoe bestemde locatie',
                        '1' => 'Er wordt overnacht op een niet daartoe bestemde locatie',
                    ])
                    ->required()
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('isErSprakeVanOvernachten');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (false);
                    }),
                Radio::make('isErGebruikVanAlcoholEnDrugs')
                    ->label('Is er gebruik van alcohol en drugs?')
                    ->options([
                        '0' => 'Niet aanwezig',
                        '0.5' => 'Aanwezig, zonder risicoverwachting',
                        '1' => 'Aanwezig, met risicoverwachting',
                    ])
                    ->required()
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('isErGebruikVanAlcoholEnDrugs');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (false);
                    }),
                Radio::make('watIsHetAantalGelijktijdigAanwezigPersonen')
                    ->label('Wat is het aantal gelijktijdig aanwezig personen?')
                    ->options([
                        '0' => 'Minder dan 150',
                        '0.25' => '150 - 2.000',
                        '0.5' => '2.000 - 5.000',
                        '0.75' => '5.000 - 10.000',
                        '1' => '10.000 - 15.000',
                        '1.25' => '> 15.000',
                    ])
                    ->required()
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('watIsHetAantalGelijktijdigAanwezigPersonen');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (false);
                    }),
                Radio::make('inWelkSeizoenVindtHetEvenementPlaats')
                    ->label('In welk seizoen vindt het evenement plaats?')
                    ->options([
                        '0.25' => 'Lente of herfst',
                        '0.5' => 'Zomer of winter',
                    ])
                    ->required()
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('inWelkSeizoenVindtHetEvenementPlaats');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (false);
                    }),
                Radio::make('inWelkeLocatieVindtHetEvenementPlaats')
                    ->label('In welke locatie vindt het evenement plaats?')
                    ->options([
                        '0.25' => 'In een gebouw, als een daartoe ingerichte evenementenlocatie',
                        '0.75' => 'In een gebouw, als een niet daartoe ingerichte evenementenlocatie',
                        '0.75__2' => 'In een bouwsel',
                        '0.5' => 'In de open lucht, op een daartoe ingericht evenemententerrein',
                        '0.75__3' => 'In de open lucht, op een niet daartoe ingericht evenemententerrein',
                        '1' => 'Op, aan of in het water',
                    ])
                    ->required()
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('inWelkeLocatieVindtHetEvenementPlaats');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (false);
                    }),
                Radio::make('opWelkSoortOndergrondVindtHetEvenementPlaats')
                    ->label('Op welk soort ondergrond vindt het evenement plaats?')
                    ->options([
                        '0.25' => 'Verharde ondergrond',
                        '0.5' => 'Onverharde ondergrond, vochtdoorlatend',
                        '0.75' => 'Onverharde ondergrond, drassig',
                    ])
                    ->required()
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('opWelkSoortOndergrondVindtHetEvenementPlaats');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (false);
                    }),
                Radio::make('watIsDeTijdsduurVanHetEvenement')
                    ->label('Wat is de tijdsduur van het evenement?')
                    ->options([
                        '0' => 'Minder dan 3 uur tijdens daguren',
                        '0.25' => 'Minder dan 3 uur tijdens avond- en nachturen',
                        '0.5' => 'Tijdsduur van 3-12 uren tijdens de daguren',
                        '0.75' => 'Tijdsduur van 3 - 12 uren tijdens de avond- en nachturen',
                        '1' => 'Hele dag (tijdsduur tussen 12 en 24 uur)',
                        '1.25' => 'Meerdere aaneengesloten dagen',
                    ])
                    ->required()
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('watIsDeTijdsduurVanHetEvenement');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (false);
                    }),
                Radio::make('welkeBeschikbaarheidVanAanEnAfvoerwegenIsVanToepassing')
                    ->label('Welke beschikbaarheid van aan- en afvoerwegen is van toepassing?')
                    ->options([
                        '1' => 'Geen aan- en afvoerwegen',
                        '0.75' => 'Matige aan- en afvoerwegen',
                        '0.5' => 'Redelijke aan- en afvoerwegen',
                        '0' => 'Goede aan- en afvoerwegen',
                    ])
                    ->required()
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('welkeBeschikbaarheidVanAanEnAfvoerwegenIsVanToepassing');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (false);
                    }),
                TextEntry::make('risicoClassificatieContent')
                    ->hiddenLabel()
                    ->state(new HtmlString('<p>Op basis van uw antwoorden is de voorlopige behandelclassificatie: <strong>{{risicoClassificatie}}</strong></p>'))
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('risicoClassificatieContent');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return true || (false);
                    }),
            ]);
    }
}
