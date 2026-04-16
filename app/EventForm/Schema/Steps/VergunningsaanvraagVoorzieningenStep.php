<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use App\EventForm\Template\LabelRenderer;
use Dotswan\MapPicker\Fields\Map;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\HtmlString;

/**
 * @openforms-step-uuid f4e91db5-fd74-4eba-b818-96ed2cc07d84
 *
 * @openforms-step-index 10
 */
final class VergunningsaanvraagVoorzieningenStep
{
    public const UUID = 'f4e91db5-fd74-4eba-b818-96ed2cc07d84';

    public static function make(): Step
    {
        return Step::make('Vergunningsaanvraag: voorzieningen')
            ->key(self::UUID)
            ->schema([
                Fieldset::make('WC\'s')
                    ->schema([
                        TextEntry::make('content23')
                            ->hiddenLabel()
                            ->state(new HtmlString('<p>U heeft aangegeven om toiletten te plaatsen (of bestaande te gebruiken) . Hierinder volgen een a<strong>antal vragen hierover.</strong></p>'))
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('content23');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('hoeveelVasteToilettenZijnBeschikbaar')
                            ->label('Hoeveel vaste toiletten zijn beschikbaar?')
                            ->numeric()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('hoeveelVasteToilettenZijnBeschikbaar');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('hoeveelTijdelijkeChemischeToilettenZijnErBeschikbaar')
                            ->label('Hoeveel tijdelijke chemische toiletten / Dixies zijn er beschikbaar?')
                            ->numeric()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('hoeveelTijdelijkeChemischeToilettenZijnErBeschikbaar');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            })
                            ->live(),
                        TextInput::make('hoeveelTijdelijkeDixiToilettenZijnErBeschikbaar')
                            ->label('Hoeveel tijdelijke gespoelde toiletten zijn er beschikbaar?')
                            ->numeric()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('hoeveelTijdelijkeDixiToilettenZijnErBeschikbaar');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || ($get('hoeveelTijdelijkeChemischeToilettenZijnErBeschikbaar') === '0');
                            }),
                        TextInput::make('welkPercentageVanDeToilettenIsVoorHeren')
                            ->label('Hoeveel toiletten zijn voor heren?')
                            ->numeric()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('welkPercentageVanDeToilettenIsVoorHeren');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('aantalToilettenDamen')
                            ->label('Hoeveel toiletten zijn voor dames?')
                            ->numeric()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('aantalToilettenDamen');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('aantalToilettenMiva')
                            ->label('Hoeveel toiletten zijn voor MIVA/rolstoelgebruikers?')
                            ->numeric()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('aantalToilettenMiva');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('handenwaspunten')
                            ->label('Hoeveel handenwaspunten worden er bij de toiletten ingericht op locatie Evenement ')
                            ->numeric()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('handenwaspunten');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        Radio::make('reinigtUDeTijdelijkeToilettenOpLocatieEvenementWatIsDeNaamVanHetEvenementVergunning')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Reinigt u de tijdelijke toiletten op locatie Evenement {{ watIsDeNaamVanHetEvenementVergunning }}?', $livewire->state()))
                            ->options([
                                'Ja' => 'Ja',
                                'Nee' => 'Nee',
                            ])
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('reinigtUDeTijdelijkeToilettenOpLocatieEvenementWatIsDeNaamVanHetEvenementVergunning');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        Radio::make('gebruikenDeTijdelijkeToilettenOpLocatieEvenementWatIsDeNaamVanHetEvenementVergunningVoorHetSpoelenOppervlaktewater')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Gebruiken de tijdelijke toiletten op locatie Evenement {{ watIsDeNaamVanHetEvenementVergunning }} voor het spoelen oppervlaktewater?', $livewire->state()))
                            ->options([
                                'Ja' => 'Ja',
                                'Nee' => 'Nee',
                            ])
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('gebruikenDeTijdelijkeToilettenOpLocatieEvenementWatIsDeNaamVanHetEvenementVergunningVoorHetSpoelenOppervlaktewater');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                    ])
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('wCs');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return true || (false);
                    }),
                Fieldset::make('Douche\'s')
                    ->schema([
                        TextEntry::make('content24')
                            ->hiddenLabel()
                            ->state(new HtmlString('<p>U heeft aangegeven, dat er douches geplaatst worden (of bestaande gebruiken). Hieronder volgen een aantal vragen hierover.</p>'))
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('content24');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('hoeveelVasteDouchevoorzieningenZijnBeschikbaar')
                            ->label('Hoeveel vaste douchevoorzieningen zijn beschikbaar?')
                            ->numeric()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('hoeveelVasteDouchevoorzieningenZijnBeschikbaar');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('hoeveelTijdelijkeDouchevoorzieningenZijnBeschikbaar')
                            ->label('Hoeveel tijdelijke douchevoorzieningen zijn beschikbaar?')
                            ->numeric()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('hoeveelTijdelijkeDouchevoorzieningenZijnBeschikbaar');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        Radio::make('wordenDeDouchesTussentijdsSchoonGemaakt')
                            ->label('Worden de douches tussentijds schoon gemaakt?')
                            ->options([
                                'Ja' => 'Ja',
                                'Nee' => 'Nee',
                            ])
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('wordenDeDouchesTussentijdsSchoonGemaakt');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                    ])
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('douches');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return true || (false);
                    }),
                Fieldset::make('EHBO')
                    ->schema([
                        TextEntry::make('content25')
                            ->hiddenLabel()
                            ->state(new HtmlString('<p>U heeft aangegeven extra medische voorzieningen te treffen (EHBO). Hieronder volgen een aantal vragen daarover.</p><p>Meer informatie vind u op de website van <a href="https://www.evenementenz.org/wp/veldnorm/ " target="_blank" rel="noopener noreferrer">Veldnorm Evenementenzorg</a>.</p>'))
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('content25');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('aantalVasteEersteHulpposten')
                            ->label('Aantal vaste eerste hulpposten')
                            ->numeric()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('aantalVasteEersteHulpposten');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('aantalMobieleEersteHulpteams')
                            ->label('Aantal mobiele eerste hulpteams')
                            ->numeric()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('aantalMobieleEersteHulpteams');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('aantalEersteHulpverlenersMetNiveauBasisEersteHulp')
                            ->label('Aantal Eerste hulpverleners met niveau \'Basis eerste hulp\'')
                            ->numeric()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('aantalEersteHulpverlenersMetNiveauBasisEersteHulp');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('aantalEersteHulpverlenersMetNiveauEvenementenEersteHulp')
                            ->label('Aantal Eerste hulpverleners met niveau \'Evenementen eerste hulp\'')
                            ->numeric()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('aantalEersteHulpverlenersMetNiveauEvenementenEersteHulp');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('aantalZorgprofessionalsMetNiveauBasisZorg')
                            ->label('Aantal Zorgprofessionals met niveau \'Basis Zorg\'')
                            ->numeric()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('aantalZorgprofessionalsMetNiveauBasisZorg');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('aantalZorgprofessionalsMetNiveauSpoedZorg')
                            ->label('Aantal Zorgprofessionals met niveau \'Spoed Zorg\'')
                            ->numeric()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('aantalZorgprofessionalsMetNiveauSpoedZorg');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('aantalZorgprofessionalsMetNiveauMedischeZorg')
                            ->label('Aantal Zorgprofessionals met niveau \'Medische Zorg\'')
                            ->numeric()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('aantalZorgprofessionalsMetNiveauMedischeZorg');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('aantalZorgprofessionalsMetNiveauSpecialistischeSpoedzorg')
                            ->label('Aantal Zorgprofessionals met niveau \'Specialistische Spoedzorg\'')
                            ->numeric()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('aantalZorgprofessionalsMetNiveauSpecialistischeSpoedzorg');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('aantalZorgprofessionalsMetNiveauArtsenSpecialistischeSpoedzorg')
                            ->label('Aantal Zorgprofessionals met niveau \'Artsen specialistische Spoedzorg\'')
                            ->numeric()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('aantalZorgprofessionalsMetNiveauArtsenSpecialistischeSpoedzorg');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('welkeOrganisatieVerzorgtDeEersteHulp')
                            ->label('Welke organisatie verzorgt de eerste hulp?')
                            ->required()
                            ->maxLength(1000)
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('welkeOrganisatieVerzorgtDeEersteHulp');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                    ])
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('ehbo');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return true || (false);
                    }),
                Fieldset::make('Verzorging van kinderen jonger dan 12 jaar')
                    ->schema([
                        TextInput::make('voorHoeveelKinderenInTotaalJongerDan12JaarIsVerzorgingNodig')
                            ->label('Voor hoeveel kinderen in totaal jonger dan 12 jaar is verzorging nodig?')
                            ->numeric()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('voorHoeveelKinderenInTotaalJongerDan12JaarIsVerzorgingNodig');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('hoeveelVanHetTotaalAantalKinderenOnder12JaarValtInDeLeeftijdscategorieVan04Jaar')
                            ->label('Hoeveel van het totaal aantal kinderen onder 12 jaar valt in de leeftijdscategorie van 0-4 jaar?')
                            ->numeric()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('hoeveelVanHetTotaalAantalKinderenOnder12JaarValtInDeLeeftijdscategorieVan04Jaar');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('hoeveelVanHetTotaalAantalKinderenOnder12JaarValtInDeLeeftijdscategorieVan512Jaar')
                            ->label('Hoeveel van het totaal aantal kinderen onder 12 jaar valt in de leeftijdscategorie van 5-12 jaar?')
                            ->numeric()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('hoeveelVanHetTotaalAantalKinderenOnder12JaarValtInDeLeeftijdscategorieVan512Jaar');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        Repeater::make('opWelkeLocatieOfLocatiesVindErOpvangVanDeKinderenOnder12JaarPlaats')
                            ->label('Op welke locatie of locaties vind er opvang van de kinderen onder 12 jaar plaats?')
                            ->schema([
                                Map::make('locatieVanOpvangVanDeKinderenOnder12Jaar')
                                    ->label('Locatie van opvang van de kinderen onder 12 jaar')
                                    ->defaultLocation(50.8514, 5.6910)
                                    ->zoom(11)
                                    ->geoMan(true)
                                    ->geoManEditable(true)
                                    ->drawPolygon(false)
                                    ->drawPolyline(false)
                                    ->drawMarker(true)
                                    ->drawCircle(false)
                                    ->drawRectangle(false)
                                    ->extraStyles(['min-height: 25rem', 'border-radius: 0.5rem'])
                                    ->columnSpanFull()
                                    ->required()
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('locatieVanOpvangVanDeKinderenOnder12Jaar');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                            ])
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('opWelkeLocatieOfLocatiesVindErOpvangVanDeKinderenOnder12JaarPlaats');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                    ])
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('verzorgingVanKinderenJongerDan12Jaar');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return true || (false);
                    }),
                Fieldset::make('Overnachtingen')
                    ->schema([
                        TextInput::make('voorHoeveelMensenVerzorgtUOvernachtingenTijdensUwEvenement1')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Voor hoeveel mensen verzorgt u overnachtingen tijdens uw Evenement {{ watIsDeNaamVanHetEvenementVergunning }}?', $livewire->state()))
                            ->numeric()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('voorHoeveelMensenVerzorgtUOvernachtingenTijdensUwEvenement1');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        Radio::make('isErSprakeVanOvernachtenDoorPubliekDeelnemers')
                            ->label('Is er sprake van overnachten door publiek/deelnemers?')
                            ->options([
                                'Ja' => 'Ja',
                                'Nee' => 'Nee',
                            ])
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('isErSprakeVanOvernachtenDoorPubliekDeelnemers');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        Repeater::make('opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPubliekDeelnemers1')
                            ->label('Op welke locatie of locaties is er sprake van overnachten door publiek/deelnemers?')
                            ->schema([
                                Map::make('locatieVanOvernachtenDoorPubliekDeelnemers')
                                    ->label('Locatie van overnachten door publiek/deelnemers')
                                    ->defaultLocation(50.8514, 5.6910)
                                    ->zoom(11)
                                    ->geoMan(true)
                                    ->geoManEditable(true)
                                    ->drawPolygon(false)
                                    ->drawPolyline(false)
                                    ->drawMarker(true)
                                    ->drawCircle(false)
                                    ->drawRectangle(false)
                                    ->extraStyles(['min-height: 25rem', 'border-radius: 0.5rem'])
                                    ->columnSpanFull()
                                    ->required()
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('locatieVanOvernachtenDoorPubliekDeelnemers');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                            ])
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPubliekDeelnemers1');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return true || (false);
                            }),
                        Radio::make('isErSprakeVanOvernachtenDoorPubliekDeelnemers1')
                            ->label('Is er sprake van overnachten door personeel/organisatie?')
                            ->options([
                                'Ja' => 'Ja',
                                'Nee' => 'Nee',
                            ])
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('isErSprakeVanOvernachtenDoorPubliekDeelnemers1');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        Repeater::make('opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPersoneelOrganisatie2')
                            ->label('Op welke locatie of locaties is er sprake van overnachten door personeel/organisatie?')
                            ->schema([
                                Map::make('locatieVanOvernachtenDoorPersoneelOrganisatie1')
                                    ->label('Locatie van overnachten door personeel/organisatie')
                                    ->defaultLocation(50.8514, 5.6910)
                                    ->zoom(11)
                                    ->geoMan(true)
                                    ->geoManEditable(true)
                                    ->drawPolygon(false)
                                    ->drawPolyline(false)
                                    ->drawMarker(true)
                                    ->drawCircle(false)
                                    ->drawRectangle(false)
                                    ->extraStyles(['min-height: 25rem', 'border-radius: 0.5rem'])
                                    ->columnSpanFull()
                                    ->required()
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('locatieVanOvernachtenDoorPersoneelOrganisatie1');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                            ])
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPersoneelOrganisatie2');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return true || (false);
                            }),
                    ])
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('overnachtingen');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return true || (false);
                    }),
                Fieldset::make('Bouwsels')
                    ->schema([
                        TextEntry::make('content26')
                            ->hiddenLabel()
                            ->state(new HtmlString('<p>U heeft aangegeven, dat er diverse bouwsels geplaatst worden. Wilt u hier meer infomatie verstrekken over deze bouwsels?</p>'))
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('content26');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('watIsHetMaximaleAantalPersonenDatTijdensUwEvenementXAanwezigIsInEenTentOfAndereBeslotenRuimtePodiumBouwwerkEtc')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Wat is het maximale aantal personen dat tijdens uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} aanwezig is in een tent of andere besloten ruimte (podium, bouwwerk etc)?', $livewire->state()))
                            ->numeric()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('watIsHetMaximaleAantalPersonenDatTijdensUwEvenementXAanwezigIsInEenTentOfAndereBeslotenRuimtePodiumBouwwerkEtc');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return true || (false);
                            }),
                    ])
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('bouwsels');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return true || (false);
                    }),
                Fieldset::make('Beveiligers')
                    ->schema([
                        TextEntry::make('content36')
                            ->hiddenLabel()
                            ->state(new HtmlString('<p>U heeft aangegeven, dat u beveiligers wilt inhuren. Hieronder volgen een aantal vragen daarover.</p>'))
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('content36');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        Textarea::make('gegevensBeveiligingsorganisatieOpLocatieEvenementX1')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Gegevens beveiligingsorganisatie op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}', $livewire->state()))
                            ->required()
                            ->maxLength(10000)
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('gegevensBeveiligingsorganisatieOpLocatieEvenementX1');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('vergunningnummerBeveiligingsorganisatie1')
                            ->label('Vergunningnummer beveiligingsorganisatie')
                            ->numeric()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('vergunningnummerBeveiligingsorganisatie1');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('vestigingsplaatsBeveiligingsorganisatie1')
                            ->label('Vestigingsplaats beveiligingsorganisatie')
                            ->required()
                            ->maxLength(1000)
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('vestigingsplaatsBeveiligingsorganisatie1');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('aantalBeveiligersOpLocatieEvenementX1')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Aantal beveiligers op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}', $livewire->state()))
                            ->numeric()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('aantalBeveiligersOpLocatieEvenementX1');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                    ])
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('beveiligers1');
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
