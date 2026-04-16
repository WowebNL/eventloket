<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use Dotswan\MapPicker\Fields\Map;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\HtmlString;

/**
 * @openforms-step-uuid e8f00982-ee47-4bec-bf31-a5c8d1b05e5e
 *
 * @openforms-step-index 14
 */
final class VergunningaanvraagOverigStep
{
    public static function make(): Step
    {
        return Step::make('Vergunningaanvraag: overig')
            ->schema([
                Fieldset::make('Voorwerpen op de weg')
                    ->schema([
                        Placeholder::make('content32')
                            ->content(new HtmlString('<p>U hebt aangegeven grote voortuigen of andere voorwerpen op de weg te willen plaatsen.</p>')),
                        Repeater::make('geefAanOpWelkeDataEnTijdenUDeVoorwerpenWiltPlaatsenOpDeOpenbareWegOfGroteVoertuigenWiltParkerenInDeBuurtVanHetEvenement')
                            ->label('Geef aan op welke data en tijden u de voorwerpen wilt plaatsen op de openbare weg of grote voertuigen wilt parkeren in de buurt van het evenement')
                            ->schema([
                                TextInput::make('voorwerp')
                                    ->label('Voorwerp')
                                    ->required()
                                    ->maxLength(1000),
                                Map::make('positieVanHetVoorwerp')
                                    ->label('Positie van het voorwerp')
                                    ->required(),
                                DateTimePicker::make('startTijdstipVoorwerp')
                                    ->label('Start tijdstip')
                                    ->required(),
                                DateTimePicker::make('eindTijdstipVoorwerp')
                                    ->label('Eind tijdstip')
                                    ->required(),
                            ]),
                        Textarea::make('vulHierEventueelInformatieInOverHetPlaatsenVanVoorwerpenOpDeOpenbareWegOfHetParkerenVanGroteVoertuigen')
                            ->label('Vul hier eventueel informatie in over het plaatsen van voorwerpen op de openbare weg of het parkeren van grote voertuigen.')
                            ->maxLength(10000),
                    ])
                    ->hidden(),
                Fieldset::make('Verkeersregelaars')
                    ->schema([
                        Placeholder::make('content33')
                            ->content(new HtmlString('<p>U heeft aangegeven, dat u verkeersregelaars wilt inzetten. Hieronder volgen een aantal vragen hierover.</p>')),
                        Radio::make('huurtUDeVerkeersregelaarsInBijEenDaarinGespecialiseerdBedrijfOrganisatie')
                            ->label('Huurt u de verkeersregelaars in bij een daarin gespecialiseerd bedrijf/organisatie?')
                            ->required()
                            ->live(),
                        Textarea::make('zijnDeInTeZettenPersonenBeroepsmatigeVerkeersregelaarsOfIsErSprakeVanEvenementenverkeersregelaars')
                            ->label('Zijn de in te zetten personen beroepsmatige verkeersregelaars of is er sprake van evenementenverkeersregelaars?')
                            ->required()
                            ->maxLength(10000)
                            ->visible(fn (Get $get): bool => $get('huurtUDeVerkeersregelaarsInBijEenDaarinGespecialiseerdBedrijfOrganisatie') === 'Ja'),
                        Placeholder::make('content34')
                            ->content(new HtmlString('<p>In geval van zelf geworven verkeersregelaars dienen de Verkeersregelaars een digitale instructie te hebben gevolgd. Kijk voor meer informatie op de website van <a href="https://verkeersregelaarsexamen.nl" target="_blank" rel="noopener noreferrer">Verkeersregelaarsexamen</a>.</p>'))
                            ->visible(fn (Get $get): bool => $get('huurtUDeVerkeersregelaarsInBijEenDaarinGespecialiseerdBedrijfOrganisatie') === 'Nee'),
                        TextInput::make('hoeveelVerkeersregelaarsWiltUInzetten')
                            ->label('Hoeveel verkeersregelaars wilt u inzetten?')
                            ->numeric()
                            ->required(),
                    ])
                    ->hidden(),
                Fieldset::make('Vervoersmaatregelen')
                    ->schema([
                        CheckboxList::make('uHeeftAangegevenDatUExtraVervoersmaatregelenWiltNemenVoorBezoekersVanUwEvenementXKruisHierAanWatVanToepassingIs')
                            ->label('U heeft aangegeven, dat u extra vervoersmaatregelen wilt nemen voor bezoekers van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}. Kruis hier aan, wat van toepassing is')
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
                            ->visible(fn (Get $get): bool => in_array('anders', (array) $get('uHeeftAangegevenDatUExtraVervoersmaatregelenWiltNemenVoorBezoekersVanUwEvenementXKruisHierAanWatVanToepassingIs'), true)),
                        Textarea::make('metWelkeOpenbaarVervoermaatschappijenHeeftUExtraAfsprakenGemaaktOverHetOpenbaarVervoer')
                            ->label('Met welke openbaar vervoermaatschappijen heeft u extra afspraken gemaakt over het openbaar vervoer?')
                            ->required()
                            ->maxLength(10000),
                    ])
                    ->hidden(),
                Fieldset::make('Promotie en communicatie')
                    ->schema([
                        Radio::make('wiltUPromotieMakenVoorUwEvenement')
                            ->label('Wilt u promotie maken voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?')
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
                            ->visible(fn (Get $get): bool => $get('wiltUPromotieMakenVoorUwEvenement') === 'Ja'),
                        CheckboxList::make('hoeWiltUPromotieMakenVoorUwEvenement')
                            ->label('Hoe wilt u promotie maken voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?')
                            ->options([
                                'driehoeksBorden' => '(Driehoeks)borden',
                                'posters' => 'Posters',
                                'flyers' => 'Flyers',
                                'spandoeken' => 'Spandoeken',
                                'vlaggen' => 'Vlaggen',
                                'anders' => 'Anders',
                            ])
                            ->required()
                            ->visible(fn (Get $get): bool => $get('wiltUPromotieMakenVoorUwEvenement') === 'Ja')
                            ->live(),
                        Textarea::make('opWelkeAndereManierWiltUPromotieMaken')
                            ->label('Op welke andere manier wilt u promotie maken?')
                            ->required()
                            ->maxLength(10000)
                            ->visible(fn (Get $get): bool => in_array('anders', (array) $get('hoeWiltUPromotieMakenVoorUwEvenement'), true)),
                        TextInput::make('websiteVanUwEvenement')
                            ->label('Website van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}')
                            ->maxLength(1000),
                        TextInput::make('facebookVanUwEvenement1')
                            ->label('Facebookpagina van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}')
                            ->maxLength(1000),
                        TextInput::make('xPaginaVanUwEvenementWatIsDeNaamVanHetEvenementVergunning')
                            ->label('X-pagina van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}')
                            ->maxLength(1000),
                    ]),
                Fieldset::make('Omwonenden communicatie')
                    ->schema([
                        Radio::make('geeftUOmwonendenEnNabijgelegenBedrijvenVoorafInformatieOverUwEvenementX')
                            ->label('Geeft u omwonenden en nabijgelegen bedrijven vooraf informatie over uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?')
                            ->required()
                            ->live(),
                        Textarea::make('opWelkeWijzeInformeertUHen')
                            ->label('Op welke wijze informeert u hen?')
                            ->required()
                            ->maxLength(10000)
                            ->visible(fn (Get $get): bool => $get('geeftUOmwonendenEnNabijgelegenBedrijvenVoorafInformatieOverUwEvenementX') === 'Ja'),
                        FileUpload::make('wiltUDeInformatieTekstAanDeOmwonendeAlsBijlageToevoegen')
                            ->label('Wilt u de informatie-tekst aan de omwonende als bijlage toevoegen?'),
                    ]),
                Fieldset::make('Organisatorische achtergrond')
                    ->schema([
                        Radio::make('organiseertUUwEvenementXVoorDeEersteKeer')
                            ->label('Organiseert u uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} voor de eerste keer?')
                            ->required()
                            ->live(),
                        Textarea::make('welkeErvaringHeeftDeOrganisatorMetHetOrganiserenVanEvenementen')
                            ->label('Welke ervaring heeft de organisator met het organiseren van evenementen?')
                            ->maxLength(10000)
                            ->visible(fn (Get $get): bool => $get('organiseertUUwEvenementXVoorDeEersteKeer') === 'Nee'),
                        Textarea::make('welkeRelevanteErvaringHeeftHetPersoneelDatDeOrganisatorInhuurtViaIntermediairs')
                            ->label('Welke relevante ervaring heeft het personeel dat de organisator inhuurt via intermediairs?')
                            ->maxLength(10000)
                            ->visible(fn (Get $get): bool => $get('organiseertUUwEvenementXVoorDeEersteKeer') === 'Nee'),
                        Textarea::make('welkeRelevanteErvaringHeeftHetPersoneelVanOnderAannemersAanWieDeOrganisatorWerkUitbesteedt')
                            ->label('Welke relevante ervaring heeft het personeel van (onder)aannemers aan wie de organisator werk uitbesteedt?')
                            ->maxLength(10000)
                            ->visible(fn (Get $get): bool => $get('organiseertUUwEvenementXVoorDeEersteKeer') === 'Nee'),
                        Textarea::make('welkeRelevanteErvaringHebbenDeVrijwilligersDieDeOrganisatorInzet')
                            ->label('Welke relevante ervaring hebben de vrijwilligers die de organisator  inzet?')
                            ->maxLength(10000)
                            ->visible(fn (Get $get): bool => $get('organiseertUUwEvenementXVoorDeEersteKeer') === 'Nee'),
                    ]),
                Fieldset::make('Huisregels en flankerende evenementen')
                    ->schema([
                        Radio::make('hanteertUHuisregelsVoorUwEvenementX')
                            ->label('Hanteert u huisregels voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?')
                            ->required()
                            ->live(),
                        FileUpload::make('uKuntHierHetHuisregelementUploaden')
                            ->label('U kunt hier het huisregelement uploaden')
                            ->visible(fn (Get $get): bool => $get('hanteertUHuisregelsVoorUwEvenementX') === 'Ja'),
                        Radio::make('organiseertUOokFlankerendeEvenementenSideEventsTijdensUwEvenementEvenementNaamSittard2024')
                            ->label('Organiseert u ook flankerende evenementen (side events) tijdens uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?')
                            ->required()
                            ->live(),
                        Textarea::make('lichtDeSideEventsToe')
                            ->label('Licht de side events toe')
                            ->required()
                            ->maxLength(10000)
                            ->visible(fn (Get $get): bool => $get('organiseertUOokFlankerendeEvenementenSideEventsTijdensUwEvenementEvenementNaamSittard2024') === 'Ja'),
                    ]),
                Fieldset::make('Verzekering')
                    ->schema([
                        Radio::make('heeftUEenEvenementenverzekeringAfgeslotenVoorUwEvenement')
                            ->label('Heeft u een evenementenverzekering afgesloten voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?')
                            ->required()
                            ->live(),
                        FileUpload::make('uploadDeVerzekeringspolis')
                            ->label('Upload de verzekeringspolis')
                            ->visible(fn (Get $get): bool => $get('heeftUEenEvenementenverzekeringAfgeslotenVoorUwEvenement') === 'Ja'),
                    ]),
            ]);
    }
}
