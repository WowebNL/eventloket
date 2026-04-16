<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use App\EventForm\Components\AddressNL;
use App\EventForm\Template\LabelRenderer;
use Dotswan\MapPicker\Fields\Map;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\HtmlString;

/**
 * @openforms-step-uuid 2186344f-9821-45d1-bd52-9900ae15fcb6
 *
 * @openforms-step-index 2
 */
final class LocatieVanHetEvenement2Step
{
    public const UUID = '2186344f-9821-45d1-bd52-9900ae15fcb6';

    public static function make(): Step
    {
        return Step::make('Locatie')
            ->key(self::UUID)
            ->schema([
                CheckboxList::make('waarVindtHetEvenementPlaats')
                    ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Waar vindt het evenement {{ watIsDeNaamVanHetEvenementVergunning }} plaats?', $livewire->state()))
                    ->options([
                        'gebouw' => 'In een gebouw of meerdere gebouwen',
                        'buiten' => 'Buiten op één of meerdere plaatsen',
                        'route' => 'Op een route',
                    ])
                    ->required()
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('waarVindtHetEvenementPlaats');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (false);
                    })
                    ->live(),
                Repeater::make('adresVanDeGebouwEn')
                    ->label('Adres van de gebouw(en)')
                    ->schema([
                        TextInput::make('naamVanDeLocatieGebouw')
                            ->label('Naam van de locatie')
                            ->required()
                            ->maxLength(1000)
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('naamVanDeLocatieGebouw');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        AddressNL::make('adresVanHetGebouwWaarUwEvenementPlaatsvindt1', 'Adres van het gebouw waar uw evenement plaatsvindt.')
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('adresVanHetGebouwWaarUwEvenementPlaatsvindt1');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                    ])
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('adresVanDeGebouwEn');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return true || (false);
                    }),
                Repeater::make('locatieSOpKaart')
                    ->label('Locatie(s) op kaart')
                    ->schema([
                        TextInput::make('naamVanDeLocatieKaart')
                            ->label('Naam van de locatie')
                            ->required()
                            ->maxLength(1000)
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('naamVanDeLocatieKaart');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        Map::make('buitenLocatieVanHetEvenement')
                            ->label('Buiten locatie van het evenement')
                            ->defaultLocation(50.8514, 5.6910)
                            ->zoom(11)
                            ->geoMan(true)
                            ->geoManEditable(true)
                            ->drawPolygon(true)
                            ->drawPolyline(false)
                            ->drawMarker(false)
                            ->drawCircle(false)
                            ->drawRectangle(false)
                            ->extraStyles(['min-height: 25rem', 'border-radius: 0.5rem'])
                            ->columnSpanFull()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('buitenLocatieVanHetEvenement');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                    ])
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('locatieSOpKaart');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return true || (false);
                    }),
                Fieldset::make('Route')
                    ->schema([
                        TextEntry::make('infoGpx1')
                            ->hiddenLabel()
                            ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>Wanneer het een eenvoudige route betreft (bijvoorbeeld voor een processie), dan kun je hieronder de route intekenen op de kaart.</p><p>Ingeval het een complexe route betreft (bijvoorbeeld een wielertocht), dan wordt aanbevolen om de route op de kaart globaal in te tekenen, zodat de applicatie kan herkennen door welke gemeenten de route gaat (en deze daarover informeren). Voor de detailroute bieden we hieronder de mogelijkheid voor het uploaden van een GPX bestand.</p>', $livewire->state())))
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('infoGpx1');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        Repeater::make('routesOpKaart')
                            ->label('Route op kaart')
                            ->schema([
                                Map::make('routeVanHetEvenement')
                                    ->label('Route van het evenement')
                                    ->defaultLocation(50.8514, 5.6910)
                                    ->zoom(11)
                                    ->geoMan(true)
                                    ->geoManEditable(true)
                                    ->drawPolygon(false)
                                    ->drawPolyline(true)
                                    ->drawMarker(false)
                                    ->drawCircle(false)
                                    ->drawRectangle(false)
                                    ->extraStyles(['min-height: 25rem', 'border-radius: 0.5rem'])
                                    ->columnSpanFull()
                                    ->required()
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('routeVanHetEvenement');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                            ])
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('routesOpKaart');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        FileUpload::make('gpxBestandVanDeRoute')
                            ->label('GPX bestand van de route')
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('gpxBestandVanDeRoute');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('naamVanDeRoute')
                            ->label('Naam van de route')
                            ->required()
                            ->maxLength(1000)
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('naamVanDeRoute');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        Select::make('watVoorEvenementGaatPlaatsvindenOpDeRoute1')
                            ->label('Wat voor evenement gaat plaatsvinden op de route?')
                            ->options([
                                'fietstochtGeenWedstrijd' => 'Fietstocht - geen wedstrijd',
                                'fietstochtWedstrijd' => 'Fietstocht - wedstrijd',
                                'gemotoriseerdeToertochtGeenWedstrijd' => 'Gemotoriseerde toertocht - geen wedstrijd',
                                'gemotoriseerdeToertochtWedstrijd' => 'Gemotoriseerde toertocht - wedstrijd',
                                'wandeltochtGeenWedstrijd' => 'Wandeltocht - geen wedstrijd',
                                'wandeltochtWedstrijd' => 'Wandeltocht - wedstrijd',
                                'A112' => 'Carnavalsoptocht',
                                'A113' => 'Hardloopwedstijd',
                                'A114' => 'Overig',
                            ])
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('watVoorEvenementGaatPlaatsvindenOpDeRoute1');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            })
                            ->live(),
                        Textarea::make('welkSoortRouteEvenementBetreftUwEvenementX')
                            ->label('Welk soort evenement vindt plaats op de route?')
                            ->required()
                            ->maxLength(10000)
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('welkSoortRouteEvenementBetreftUwEvenementX');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (! ($get('watVoorEvenementGaatPlaatsvindenOpDeRoute1') === 'A114'));
                            }),
                        CheckboxList::make('komtUwRouteOverWegenVanWegbeheerdersAndersDanDeBetreffendeGemeenteZoJaKruisDezeDanAan')
                            ->label('Komt uw route over wegen van wegbeheerders, anders dan de betreffende gemeente? Zo ja, kruis deze dan aan.')
                            ->options([
                                'provincie' => 'Provincie',
                                'waterschap' => 'Waterschap',
                                'rijkswaterstaat' => 'Rijkswaterstaat',
                                'staatsbosbeheer' => 'Staatsbosbeheer',
                            ])
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('komtUwRouteOverWegenVanWegbeheerdersAndersDanDeBetreffendeGemeenteZoJaKruisDezeDanAan');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            })
                            ->live(),
                        TextEntry::make('content1')
                            ->hiddenLabel()
                            ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>Voor het gebruik van provinciale wegen, of in het geval van een wegwedstrijd die door meerdere gemeenten binnen de provincie voert dient er <a href="https://www.limburg.nl/@1161/wedstrijden-weg" target="_blank" rel="noopener noreferrer">een verzoek voor ontheffing van de openbare weg </a>gericht te worden aan de Provincie Limburg.</p>', $livewire->state())))
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('content1');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (! (in_array('provincie', (array) $get('komtUwRouteOverWegenVanWegbeheerdersAndersDanDeBetreffendeGemeenteZoJaKruisDezeDanAan'), true)));
                            }),
                        TextEntry::make('content39')
                            ->hiddenLabel()
                            ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>Voor het afgeven van een ontheffing voor het kruisen van wegen/waters van het Waterschap dient u een aanvraag te doen via <a href="https://www.waterschaplimburg.nl/overons/regels-wetgeving-0/melding-vergunning/" target="_blank" rel="noopener noreferrer">de website</a>.</p>', $livewire->state())))
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('content39');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (! (in_array('waterschap', (array) $get('komtUwRouteOverWegenVanWegbeheerdersAndersDanDeBetreffendeGemeenteZoJaKruisDezeDanAan'), true)));
                            }),
                        TextEntry::make('content41')
                            ->hiddenLabel()
                            ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>Voor het afgeven van een ontheffing voor het kruisen van wegen/waters van het Rijkswaterstaat dient u een aanvraag te doen via <a href="https://www.rijkswaterstaat.nl/wegen/wetten-regels-en-vergunningen/vergunningen-rijkswegen" target="_blank" rel="noopener noreferrer">de website van Rijkswaterstaat</a>.</p>', $livewire->state())))
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('content41');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (! (in_array('rijkswaterstaat', (array) $get('komtUwRouteOverWegenVanWegbeheerdersAndersDanDeBetreffendeGemeenteZoJaKruisDezeDanAan'), true)));
                            }),
                        TextEntry::make('content40')
                            ->hiddenLabel()
                            ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>Voor het afgeven van een ontheffing voor het kruisen van wegen/paden van het Staatsbosheer dient u een aanvraag te doen via <a href="https://www.staatsbosbeheer.nl/contact/evenementen-aanmelden" target="_blank" rel="noopener noreferrer">de website van Staatsbosbeheer</a>.</p>', $livewire->state())))
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('content40');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (! (in_array('staatsbosbeheer', (array) $get('komtUwRouteOverWegenVanWegbeheerdersAndersDanDeBetreffendeGemeenteZoJaKruisDezeDanAan'), true)));
                            }),
                        TextEntry::make('routeStartEndContent2')
                            ->hiddenLabel()
                            ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>{% if not inGemeentenResponse.line.start or not inGemeentenResponse.line.end %}</p><p>Er is nog geen route ingetekend of de route start of eindigt &nbsp;buiten de gemeenten die gebruik maken van Eventloket.&nbsp;</p><p>{% elif inGemeentenResponse.line.start_end_equal == False %}</p><p>De route start in de gemeente <strong>{{ inGemeentenResponse.line.start.name }}</strong> en eindigt in de gemeente <strong>{{ inGemeentenResponse.line.end.name }}</strong>, hierdoor kan het zijn dat u bij beide gemeenten een vergunningaanvraag moet doen. U dient vult dit formulier helemaal in voor 1 gemeente, als u de aanvraag vervolgens heeft gedaan kunt u binnen de aanvraag in Eventloket de knop “Nieuwe aanvraag” gebruiken om een nieuw aanvraag te starten waarbij (een deel van) het formulier al vooraf ingevuld is.</p><p>{% elif inGemeentenResponse.line.start_end_equal == True %}</p><p>De route start en eindigt binnen de gemeente <strong>{{ inGemeentenResponse.line.start.name }}.</strong></p><p>{% endif %}</p>', $livewire->state())))
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('routeStartEndContent2');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                    ])
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('route');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (! (in_array('route', (array) $get('waarVindtHetEvenementPlaats'), true)));
                    }),
                TextEntry::make('NotWithin')
                    ->hiddenLabel()
                    ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<h3><span style="color:#e64c4c;">Let op</span></h3><p>Een ingevoerd adres of (een deel van) een getekende route of locatie valt buiten de gemeenten die EventLoket gebruiken.</p><p>Eventloket wordt gebruikt door de gemeenten:&nbsp;{{ alleGemeenteNamen }}</p>', $livewire->state())))
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('NotWithin');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return true || (false);
                    }),
                Radio::make('userSelectGemeente')
                    ->label('De ingevoerde locatie(s) of route valt binnen of doorkruist meerdere gemeenten, wat is de gemeente waarbinnen u de aanvraag wilt doen?')
                    ->required()
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('userSelectGemeente');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return true || (false);
                    })
                    ->live(),
                TextEntry::make('contentRouteDoorkuistMeerdereGemeenteInfo')
                    ->hiddenLabel()
                    ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>De ingetekende route doorkruist de volgende gemeente(n): {{ routeDoorGemeentenNamen|join:", " }} &nbsp;U gaat de vergunningaanvraag invullen voor de gemeente&nbsp;<strong> {% get_value evenementInGemeente \'name\' %}</strong>, de overige gemeente(n) die gebruik maken van Eventloket op de route zullen automatisch geïnformeerd worden.</p>', $livewire->state())))
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('contentRouteDoorkuistMeerdereGemeenteInfo');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return true || (false);
                    }),
                TextEntry::make('content200')
                    ->hiddenLabel()
                    ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>U gaat verder met deze aanraag voor de gemeente:<strong> {% get_value evenementInGemeente \'name\' %}</strong></p>', $livewire->state())))
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('content200');
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
