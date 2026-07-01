<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use App\EventForm\Components\EventloketFileUpload;
use App\EventForm\Components\InfoText;
use App\EventForm\Components\JaNeeOptions;
use App\EventForm\Schema\Hidden;
use App\EventForm\Schema\Label;
use App\Models\Organisation;
use Dotswan\MapPicker\Fields\Map;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;

/**
 * @openforms-step-uuid e8f00982-ee47-4bec-bf31-a5c8d1b05e5e
 *
 * @openforms-step-index 14
 */
final class VergunningaanvraagOverigStep
{
    public const UUID = 'e8f00982-ee47-4bec-bf31-a5c8d1b05e5e';

    public static function make(?Organisation $organisation = null): Step
    {
        return Step::make('Vergunningaanvraag: overig')
            ->key(self::UUID)
            ->schema([
                Fieldset::make('Voorwerpen op de weg')
                    ->columns(1)
                    ->schema([
                        InfoText::info('content32', '<p>U hebt aangegeven grote voortuigen of andere voorwerpen op de weg te willen plaatsen.</p>'),
                        Repeater::make('geefAanOpWelkeDataEnTijdenUDeVoorwerpenWiltPlaatsenOpDeOpenbareWegOfGroteVoertuigenWiltParkerenInDeBuurtVanHetEvenement')
                            ->label('Geef aan op welke data en tijden u de voorwerpen wilt plaatsen op de openbare weg of grote voertuigen wilt parkeren in de buurt van het evenement')
                            ->addActionLabel('Nog een voorwerp of voertuig toevoegen')
                            ->schema([
                                TextInput::make('voorwerp')
                                    ->label('Voorwerp')
                                    ->required()
                                    ->maxLength(1000),
                                Map::make('positieVanHetVoorwerp')
                                    ->label('Positie van het voorwerp')
                                    ->defaultLocation(50.8514, 5.6910)
                                    ->zoom(11)
                                    ->maxZoom(19)
                                    ->geoMan(true)
                                    ->geoManEditable(true)
                                    ->drawPolygon(false)
                                    ->editPolygon(false)
                                    ->drawPolyline(false)
                                    ->drawMarker(true)
                                    ->drawCircle(false)
                                    ->drawCircleMarker(false)
                                    ->drawRectangle(false)
                                    ->cutPolygon(false)
                                    ->dragMode(false)
                                    ->rotateMode(false)
                                    ->showMarker(false)
                                    ->drawText(false)
                                    ->deleteLayer(true)
                                    ->showFullscreenControl(false)
                                    ->extraStyles(['min-height: 25rem', 'border-radius: 0.5rem'])
                                    ->columnSpanFull()
                                    ->showMyLocationButton(false)
                                    ->required(),
                                DateTimePicker::make('startTijdstipVoorwerp')
                                    ->label('Start tijdstip')
                                    ->seconds(false)
                                    ->required(),
                                DateTimePicker::make('eindTijdstipVoorwerp')
                                    ->label('Eind tijdstip')
                                    ->seconds(false)
                                    ->afterOrEqual('startTijdstipVoorwerp')
                                    ->validationMessages([
                                        'after_or_equal' => 'Het eind tijdstip moet op of na het start tijdstip liggen.',
                                    ])
                                    ->required(),
                            ]),
                        Textarea::make('vulHierEventueelInformatieInOverHetPlaatsenVanVoorwerpenOpDeOpenbareWegOfHetParkerenVanGroteVoertuigen')
                            ->label('Vul hier eventueel informatie in over het plaatsen van voorwerpen op de openbare weg of het parkeren van grote voertuigen.')
                            ->maxLength(10000),
                    ])
                    ->hidden(Hidden::rule('groteVoertuigen')),
                Fieldset::make('Verkeersregelaars')
                    ->columns(1)
                    ->schema([
                        InfoText::info('content33', '<p>U heeft aangegeven, dat u verkeersregelaars wilt inzetten. Hieronder volgt een aantal vragen hierover.</p>'),
                        Radio::make('huurtUDeVerkeersregelaarsInBijEenDaarinGespecialiseerdBedrijfOrganisatie')
                            ->label('Huurt u de verkeersregelaars in bij een daarin gespecialiseerd bedrijf/organisatie?')
                            ->options(JaNeeOptions::OPTIONS)
                            ->required()
                            ->live(),
                        Textarea::make('zijnDeInTeZettenPersonenBeroepsmatigeVerkeersregelaarsOfIsErSprakeVanEvenementenverkeersregelaars')
                            ->label('Zijn de in te zetten personen beroepsmatige verkeersregelaars ?')
                            ->required()
                            ->maxLength(10000)
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('zijnDeInTeZettenPersonenBeroepsmatigeVerkeersregelaarsOfIsErSprakeVanEvenementenverkeersregelaars');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! ($get('huurtUDeVerkeersregelaarsInBijEenDaarinGespecialiseerdBedrijfOrganisatie') === 'Ja');
                            }),
                        InfoText::info('content34', '<p>In geval van zelf geworven (evenementen-)verkeersregelaars dienen de Verkeersregelaars een digitale instructie te hebben gevolgd. Kijk voor meer informatie op de website van <a href="https://verkeersregelaarsexamen.nl" target="_blank" rel="noopener noreferrer">Verkeersregelaarsexamen</a>.</p>')
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('content34');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! ($get('huurtUDeVerkeersregelaarsInBijEenDaarinGespecialiseerdBedrijfOrganisatie') === 'Nee');
                            }),
                        TextInput::make('hoeveelVerkeersregelaarsWiltUInzetten')
                            ->label('Hoeveel verkeersregelaars wilt u inzetten?')
                            ->numeric()
                            ->required(),
                    ])
                    ->hidden(Hidden::rule('verkeersregelaars')),
                Fieldset::make('Vervoersmaatregelen')
                    ->columns(1)
                    ->schema([
                        CheckboxList::make('uHeeftAangegevenDatUExtraVervoersmaatregelenWiltNemenVoorBezoekersVanUwEvenementXKruisHierAanWatVanToepassingIs')
                            ->label(Label::render('U heeft aangegeven, dat u extra vervoersmaatregelen wilt nemen voor bezoekers van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}. Kruis hier aan, wat van toepassing is'))
                            ->options([
                                'extraParkeerplekkenInrichten' => 'Extra parkeerplekken inrichten',
                                'extraFietsenstallingenPlaatsen' => 'Extra fietsenstallingen plaatsen',
                                'inzettenPendelbussen' => 'Inzetten pendelbussen',
                                'extraOpenbaarVervoerRegelen' => 'Extra openbaar vervoer regelen',
                                'bezoekersStimulerenMetHetOpenbaarVervoerTeKomen' => 'Bezoekers stimuleren met het openbaar vervoer te komen',
                                'bezoekersStimulerenMetDeFietsTeKomen' => 'Bezoekers stimuleren met de fiets te komen',
                                'anders' => 'Anders',
                            ])
                            ->required()
                            ->live(),
                        Textarea::make('welkeAndereMaatregelenUWiltNemen')
                            ->label('Welke andere maatregelen u wilt nemen')
                            ->required()
                            ->maxLength(10000)
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('welkeAndereMaatregelenUWiltNemen');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! (in_array('anders', (array) $get('uHeeftAangegevenDatUExtraVervoersmaatregelenWiltNemenVoorBezoekersVanUwEvenementXKruisHierAanWatVanToepassingIs'), true));
                            }),
                        Textarea::make('metWelkeOpenbaarVervoermaatschappijenHeeftUExtraAfsprakenGemaaktOverHetOpenbaarVervoer')
                            ->label('Met welke openbaar vervoermaatschappijen heeft u extra afspraken gemaakt over het openbaar vervoer?')
                            ->required()
                            ->maxLength(10000),
                    ])
                    ->hidden(Hidden::rule('vervoersmaatregelen')),
                Fieldset::make('Promotie en communicatie')
                    ->columns(1)
                    ->schema([
                        Radio::make('wiltUPromotieMakenVoorUwEvenement')
                            ->label(Label::render('Wilt u promotie maken voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?'))
                            ->options(JaNeeOptions::OPTIONS)
                            ->required()
                            ->live(),
                        Radio::make('opWelkNiveauWiltUPromotieMaken')
                            ->label('Op welk niveau wilt u promotie maken?')
                            ->options([
                                'lokaal' => 'Lokaal',
                                'regionaal' => 'Regionaal',
                                'landelijk' => 'Landelijk',
                                'lnternationaal' => 'lnternationaal',
                            ])
                            ->required()
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('opWelkNiveauWiltUPromotieMaken');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! ($get('wiltUPromotieMakenVoorUwEvenement') === 'Ja');
                            }),
                        CheckboxList::make('hoeWiltUPromotieMakenVoorUwEvenement')
                            ->label(Label::render('Hoe wilt u promotie maken voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?'))
                            ->options([
                                'driehoeksBorden' => '(Driehoeks)borden',
                                'posters' => 'Posters',
                                'flyers' => 'Flyers',
                                'spandoeken' => 'Spandoeken',
                                'vlaggen' => 'Vlaggen',
                                'anders' => 'Anders',
                            ])
                            ->required()
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('hoeWiltUPromotieMakenVoorUwEvenement');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! ($get('wiltUPromotieMakenVoorUwEvenement') === 'Ja');
                            })
                            ->live(),
                        Textarea::make('opWelkeAndereManierWiltUPromotieMaken')
                            ->label('Op welke andere manier wilt u promotie maken?')
                            ->required()
                            ->maxLength(10000)
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('opWelkeAndereManierWiltUPromotieMaken');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! (in_array('anders', (array) $get('hoeWiltUPromotieMakenVoorUwEvenement'), true));
                            }),
                        TextInput::make('websiteVanUwEvenement')
                            ->label(Label::render('Website van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}'))
                            ->maxLength(1000),
                        TextInput::make('facebookVanUwEvenement1')
                            ->label(Label::render('Facebookpagina van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}'))
                            ->maxLength(1000),
                        TextInput::make('xPaginaVanUwEvenementWatIsDeNaamVanHetEvenementVergunning')
                            ->label(Label::render('X-pagina van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}'))
                            ->maxLength(1000),
                    ]),
                Fieldset::make('Omwonenden communicatie')
                    ->columns(1)
                    ->schema([
                        Radio::make('geeftUOmwonendenEnNabijgelegenBedrijvenVoorafInformatieOverUwEvenementX')
                            ->label(Label::render('Geeft u omwonenden en nabijgelegen bedrijven vooraf informatie over uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?'))
                            ->options(JaNeeOptions::OPTIONS)
                            ->required()
                            ->live(),
                        Textarea::make('opWelkeWijzeInformeertUHen')
                            ->label('Op welke wijze informeert u hen?')
                            ->required()
                            ->maxLength(10000)
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('opWelkeWijzeInformeertUHen');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! ($get('geeftUOmwonendenEnNabijgelegenBedrijvenVoorafInformatieOverUwEvenementX') === 'Ja');
                            }),
                        EventloketFileUpload::make('wiltUDeInformatieTekstAanDeOmwonendeAlsBijlageToevoegen', $organisation)
                            ->label('Wilt u de informatie-tekst aan de omwonende als bijlage toevoegen?'),
                    ]),
                Fieldset::make('Organisatorische achtergrond')
                    ->columns(1)
                    ->schema([
                        Radio::make('organiseertUUwEvenementXVoorDeEersteKeer')
                            ->label(Label::render('Organiseert u uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} voor de eerste keer?'))
                            ->options(JaNeeOptions::OPTIONS)
                            ->required()
                            ->live(),
                        Textarea::make('welkeErvaringHeeftDeOrganisatorMetHetOrganiserenVanEvenementen')
                            ->label('Welke ervaring heeft de organisator met het organiseren van evenementen?')
                            ->maxLength(10000)
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('welkeErvaringHeeftDeOrganisatorMetHetOrganiserenVanEvenementen');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! ($get('organiseertUUwEvenementXVoorDeEersteKeer') === 'Nee');
                            }),
                        Textarea::make('welkeRelevanteErvaringHeeftHetPersoneelDatDeOrganisatorInhuurtViaIntermediairs')
                            ->label('Welke relevante ervaring heeft het personeel dat de organisator inhuurt via intermediairs?')
                            ->maxLength(10000)
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('welkeRelevanteErvaringHeeftHetPersoneelDatDeOrganisatorInhuurtViaIntermediairs');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! ($get('organiseertUUwEvenementXVoorDeEersteKeer') === 'Nee');
                            }),
                        Textarea::make('welkeRelevanteErvaringHeeftHetPersoneelVanOnderAannemersAanWieDeOrganisatorWerkUitbesteedt')
                            ->label('Welke relevante ervaring heeft het personeel van (onder)aannemers aan wie de organisator werk uitbesteedt?')
                            ->maxLength(10000)
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('welkeRelevanteErvaringHeeftHetPersoneelVanOnderAannemersAanWieDeOrganisatorWerkUitbesteedt');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! ($get('organiseertUUwEvenementXVoorDeEersteKeer') === 'Nee');
                            }),
                        Textarea::make('welkeRelevanteErvaringHebbenDeVrijwilligersDieDeOrganisatorInzet')
                            ->label('Welke relevante ervaring hebben de vrijwilligers die de organisator  inzet?')
                            ->maxLength(10000)
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('welkeRelevanteErvaringHebbenDeVrijwilligersDieDeOrganisatorInzet');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! ($get('organiseertUUwEvenementXVoorDeEersteKeer') === 'Nee');
                            }),
                    ]),
                Fieldset::make('Huisregels en flankerende evenementen')
                    ->columns(1)
                    ->schema([
                        Radio::make('hanteertUHuisregelsVoorUwEvenementX')
                            ->label(Label::render('Hanteert u huisregels voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?'))
                            ->options(JaNeeOptions::OPTIONS)
                            ->required()
                            ->live(),
                        EventloketFileUpload::make('uKuntHierHetHuisregelementUploaden', $organisation)
                            ->label('U kunt hier het huisregelement uploaden')
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('uKuntHierHetHuisregelementUploaden');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! ($get('hanteertUHuisregelsVoorUwEvenementX') === 'Ja');
                            }),
                        Radio::make('organiseertUOokFlankerendeEvenementenSideEventsTijdensUwEvenementEvenementNaamSittard2024')
                            ->label(Label::render('Organiseert u ook flankerende evenementen (side events) tijdens uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?'))
                            ->options(JaNeeOptions::OPTIONS)
                            ->required()
                            ->live(),
                        Textarea::make('lichtDeSideEventsToe')
                            ->label('Licht de side events toe')
                            ->required()
                            ->maxLength(10000)
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('lichtDeSideEventsToe');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! ($get('organiseertUOokFlankerendeEvenementenSideEventsTijdensUwEvenementEvenementNaamSittard2024') === 'Ja');
                            }),
                    ]),
                Fieldset::make('Verzekering')
                    ->columns(1)
                    ->schema([
                        Radio::make('heeftUEenEvenementenverzekeringAfgeslotenVoorUwEvenement')
                            ->label(Label::render('Heeft u een evenementenverzekering afgesloten voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?'))
                            ->options(JaNeeOptions::OPTIONS)
                            ->required()
                            ->live(),
                        EventloketFileUpload::make('uploadDeVerzekeringspolis', $organisation)
                            ->label('Upload de verzekeringspolis')
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('uploadDeVerzekeringspolis');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! ($get('heeftUEenEvenementenverzekeringAfgeslotenVoorUwEvenement') === 'Ja');
                            }),
                        Radio::make('wiltUDeVerzekeringapolisOpEenLaterTijdstipToevoegen')
                            ->label('Wilt u de verzekeringspolis op een later tijdstip toevoegen?')
                            ->options(JaNeeOptions::OPTIONS)
                            ->required()
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('wiltUDeVerzekeringapolisOpEenLaterTijdstipToevoegen');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! ($get('heeftUEenEvenementenverzekeringAfgeslotenVoorUwEvenement') === 'Nee');
                            }),
                    ]),
            ]);
    }
}
