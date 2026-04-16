<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use Dotswan\MapPicker\Fields\Map;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
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
 * @openforms-step-uuid 661aabb7-e927-4a75-8d95-0a665c5d83fe
 *
 * @openforms-step-index 9
 */
final class VergunningaanvraagVervolgvragenStep
{
    public static function make(): Step
    {
        return Step::make('Vergunningaanvraag: kenmerken')
            ->schema([
                Fieldset::make('Versterkte muziek')
                    ->schema([
                        Placeholder::make('content5')
                            ->content(new HtmlString('<p>U heeft aangegeven, dat er sprake is van versterkte muziek. Hieronder volgen een aantal vragen hierover.</p>')),
                        CheckboxList::make('wieMaaktDeMuziekOpLocatieBijUwEvenementWatIsDeNaamVanHetEvenementVergunning')
                            ->label('Wie maakt de muziek op locatie bij uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?')
                            ->options([
                                'dj' => 'DJ',
                                'band' => 'Band',
                                'orkest' => 'Orkest',
                                'tapeArtiest' => '(Tape-)artiest',
                                'anders' => 'Anders',
                            ])
                            ->required()
                            ->hidden()
                            ->live(),
                        Textarea::make('opWelkeAndereManierWordtErMuziekGemaakt')
                            ->label('Op welke andere manier wordt er muziek gemaakt?')
                            ->required()
                            ->maxLength(10000)
                            ->visible(fn (Get $get): bool => $get('wieMaaktDeMuziekOpLocatieBijUwEvenementWatIsDeNaamVanHetEvenementVergunning.anders') === true),
                        CheckboxList::make('welkeSoortenMuziekZijnErTeHorenOpLocatieEvenementX')
                            ->label('Welke soorten muziek zijn er te horen op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}?')
                            ->options([
                                'A69' => 'Klassiek',
                                'A70' => 'Jazz',
                                'A71' => 'Dance',
                                'A72' => 'Pop (en overige)',
                            ])
                            ->required()
                            ->hidden()
                            ->live(),
                        CheckboxList::make('welkeSoortenDanceMuziekZijnErTeHorenOpLocatieEvenementX')
                            ->label('Welke soorten Dance muziek zijn er te horen op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}?')
                            ->options([
                                'acid' => 'Acid',
                                'ambient' => 'Ambient',
                                'club' => 'Club',
                                'disco' => 'Disco',
                                'drumNBass' => 'Drum \'n Bass',
                                'electro' => 'Electro',
                                'garage' => 'Garage',
                                'hardcore' => 'Hardcore',
                                'house' => 'House',
                                'hardstyle' => 'Hardstyle',
                                'jungle' => 'Jungle',
                                'lounge' => 'Lounge',
                                'techno' => 'Techno',
                                'trance' => 'Trance',
                                'edm' => 'EDM',
                            ])
                            ->required()
                            ->visible(fn (Get $get): bool => $get('welkeSoortenMuziekZijnErTeHorenOpLocatieEvenementX.A71') === true),
                        CheckboxList::make('welkeSoortenPopmuziekZijnErTeHorenOpLocatieEvenement')
                            ->label('Welke soorten popmuziek zijn er te horen op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}?')
                            ->options([
                                'blues' => 'Blues',
                                'country' => 'Country',
                                'disco' => 'Disco',
                                'funk' => 'Funk',
                                'hiphop' => 'Hiphop',
                                'hardrock' => 'Hardrock',
                                'kindermuziek' => 'Kindermuziek',
                                'metal' => 'Metal',
                                'nederlandstaligeVolksmuziek' => 'Nederlandstalige volksmuziek',
                                'carnavalsmuziek' => 'Carnavalsmuziek',
                                'punk' => 'Punk',
                                'rB' => 'R&B',
                                'rap' => 'Rap',
                                'reggae' => 'Reggae',
                                'rock' => 'Rock',
                                'rockNRollSchlager' => 'Rock \'n Roll Schlager',
                                'soul' => 'Soul',
                                'anders' => 'Anders',
                            ])
                            ->required()
                            ->visible(fn (Get $get): bool => $get('welkeSoortenMuziekZijnErTeHorenOpLocatieEvenementX.A72') === true)
                            ->live(),
                        Textarea::make('welkeAnderSoortPopmuziekIsErTeHorenOpEvenementX')
                            ->label('Welke ander soort popmuziek is er te horen op evenement {{ watIsDeNaamVanHetEvenementVergunning }}?')
                            ->required()
                            ->maxLength(10000)
                            ->visible(fn (Get $get): bool => $get('welkeSoortenPopmuziekZijnErTeHorenOpLocatieEvenement.anders') === true),
                        TextInput::make('watIsDeGeluidsbelastingInDecibelDBANorm0103DBVanUwEvenementX')
                            ->label('Wat is de geluidsbelasting in decibel (dB(A) norm - (0–103 dB)) van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?')
                            ->numeric()
                            ->required(),
                        TextInput::make('watIsDeGeluidsbelastingInDecibelDBCNorm0103DBVanUwEvenement')
                            ->label('Wat is de geluidsbelasting in decibel Db(C) norm - (0–113 dB)) van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?')
                            ->numeric()
                            ->required(),
                    ])
                    ->hidden(),
                Fieldset::make('Bouwsels &gt; 10m<sup>2</sup> ')
                    ->schema([
                        Placeholder::make('content15')
                            ->content(new HtmlString('<p>U heeft aangegeven, dat er bouwsels &gt; 10m2 zoals tenten of podia geplaatst worden. Hieronder volgen een aantal vragen hierover.</p>')),
                        CheckboxList::make('watVoorBouwselsPlaatsUOpDeLocaties')
                            ->label('Wat voor bouwsels plaats u op de locaties?')
                            ->options([
                                'A54' => 'Tent(en)',
                                'A55' => 'Podia',
                                'A56' => 'Overkappingen',
                                'A57' => 'Omheiningen',
                            ])
                            ->required()
                            ->hidden()
                            ->live(),
                        Repeater::make('tenten')
                            ->label('Welke tenten plaatst u?')
                            ->schema([
                                TextInput::make('tentnummer')
                                    ->label('Tentnummer')
                                    ->required()
                                    ->maxLength(1000),
                                TextInput::make('lengteTent')
                                    ->label('Lengte in meter')
                                    ->numeric()
                                    ->required(),
                                TextInput::make('BreedteTent')
                                    ->label('Breedte in meter')
                                    ->numeric()
                                    ->required(),
                                TextInput::make('HoogteTent')
                                    ->label('Hoogte in meter')
                                    ->numeric()
                                    ->required(),
                                Radio::make('wijzeVanVerankering')
                                    ->label('Wijze van verankering')
                                    ->options([
                                        'palenInDeGrond' => 'Palen in de grond',
                                        'betonblokken' => 'Betonblokken',
                                    ])
                                    ->required(),
                            ])
                            ->hidden(),
                        Repeater::make('podia')
                            ->label('Welke podia plaatst u?')
                            ->schema([
                                TextInput::make('podiumnummer')
                                    ->label('Podium nummer')
                                    ->required()
                                    ->maxLength(1000),
                                TextInput::make('lengtePodium')
                                    ->label('Lengte in meter')
                                    ->numeric()
                                    ->required(),
                                TextInput::make('BreedtePodium')
                                    ->label('Breedte in meter')
                                    ->numeric()
                                    ->required(),
                                TextInput::make('HoogtePodium')
                                    ->label('Hoogte in meter')
                                    ->numeric()
                                    ->required(),
                            ])
                            ->hidden(),
                        Repeater::make('overkappingen')
                            ->label('Welke overkappingen plaatst u?')
                            ->schema([
                                TextInput::make('overkappingnummer')
                                    ->label('Overkapping nummer')
                                    ->required()
                                    ->maxLength(1000),
                                TextInput::make('lengteOverkapping')
                                    ->label('Lengte in meter')
                                    ->numeric()
                                    ->required(),
                                TextInput::make('BreedteOverkapping')
                                    ->label('Breedte in meter')
                                    ->numeric()
                                    ->required(),
                                TextInput::make('HoogteOverkapping')
                                    ->label('Hoogte in meter')
                                    ->numeric()
                                    ->required(),
                                Radio::make('wijzeVanVerankering1')
                                    ->label('Wijze van verankering')
                                    ->options([
                                        'palenInDeGrond' => 'Palen in de grond',
                                        'betonblokken' => 'Betonblokken',
                                    ])
                                    ->required(),
                            ])
                            ->hidden(),
                        Textarea::make('geefEenOmschrijvingVanSoortOmheining')
                            ->label('Geef een omschrijving van soort omheining')
                            ->required()
                            ->maxLength(10000)
                            ->visible(fn (Get $get): bool => $get('watVoorBouwselsPlaatsUOpDeLocaties.A57') === true),
                        FileUpload::make('plaatstUTijdelijkeConstructiesTentenPodiaEtcDanDientUNaastHetVeiligheidsplanTevensEenDeelplanTijdelijkeConstructiesTeMakenEnTeUploadenAlsBijlage')
                            ->label('Plaatst u tijdelijke constructies (tenten, podia etc.) dan dient u naast het veiligheidsplan tevens een \'Deelplan Tijdelijke constructies\' te maken en te uploaden als bijlage.'),
                    ])
                    ->hidden(),
                Fieldset::make('Kansspelen')
                    ->schema([
                        Placeholder::make('content16')
                            ->content(new HtmlString('<p>U heeft kansspelen aangekruist.&nbsp;Hieronder volgen een aantal vragen daarover.</p>')),
                        Textarea::make('welkSoortKansspelBetreftHet')
                            ->label('Welk soort kansspel betreft het?')
                            ->required()
                            ->maxLength(10000),
                        Radio::make('isDeOrganisatieVanHetKansspelInHandenVanEenVereniging')
                            ->label('Is de organisatie van het kansspel in handen van een vereniging?')
                            ->required()
                            ->live(),
                        Radio::make('bestaatDeVereningingDieHetKansspelOrganiseertLangerDan3Jaar')
                            ->label('Bestaat de vereninging, die het kansspel organiseert langer dan 3 jaar?')
                            ->required()
                            ->visible(fn (Get $get): bool => $get('isDeOrganisatieVanHetKansspelInHandenVanEenVereniging') === 'Ja'),
                        Textarea::make('watBentUVanPlanMetDeOpbrengstVanHetKansspelTeGaanDoen')
                            ->label('Wat bent u van plan met de opbrengst van het kansspel te gaan doen?')
                            ->required()
                            ->maxLength(10000),
                        TextInput::make('geefEenIndicatieVanDeHoogteVanHetPrijzengeld')
                            ->label('Geef een indicatie van de hoogte van het prijzengeld')
                            ->numeric()->prefix('€')
                            ->required(),
                    ])
                    ->hidden(),
                Fieldset::make('Alcoholische dranken')
                    ->schema([
                        Placeholder::make('content17')
                            ->content(new HtmlString('<p>U heeft aangegeven, dat er alcoholische dranken genuttigd worden. Hieronder volgen een aantal vragen hierover.</p>')),
                        Radio::make('isEenPersoonOfOrganisatieVerantwoordelijkVoorDeAlcoholverkoop')
                            ->label('Is een persoon of organisatie verantwoordelijk voor de alcoholverkoop?')
                            ->options([
                                'persoon' => 'Persoon',
                                'organisatie' => 'Organisatie',
                            ])
                            ->required()
                            ->live(),
                        Fieldset::make('Persoongroep')
                            ->schema([
                                TextInput::make('voornaamVanDePersoonAlcohol')
                                    ->label('Voornaam van de persoon')
                                    ->required()
                                    ->maxLength(1000),
                                TextInput::make('achternaamVanDePersoon1Alcohol')
                                    ->label('Achternaam van de persoon')
                                    ->required()
                                    ->maxLength(1000),
                                DatePicker::make('geboortedatumPersoonAlcohol')
                                    ->label('Geboortedatum persoon')
                                    ->required(),
                                TextInput::make('geboorteplaatsPersoonAlcohol')
                                    ->label('Geboorteplaats persoon')
                                    ->required()
                                    ->maxLength(1000),
                            ])
                            ->visible(fn (Get $get): bool => $get('isEenPersoonOfOrganisatieVerantwoordelijkVoorDeAlcoholverkoop') === 'persoon'),
                        Fieldset::make('Organisatiegroep')
                            ->schema([
                                TextInput::make('watIsDeNaamVanDeOrganisatie')
                                    ->label('Wat is de naam van de organisatie?')
                                    ->required()
                                    ->maxLength(1000),
                                TextInput::make('watIsHetTelefoonnummerVanDeOrganisatie')
                                    ->label('Wat is het telefoonnummer van de organisatie?')
                                    ->tel()
                                    ->required(),
                            ])
                            ->visible(fn (Get $get): bool => $get('isEenPersoonOfOrganisatieVerantwoordelijkVoorDeAlcoholverkoop') === 'organisatie'),
                        Repeater::make('watZijnDeLocatiesWaarUDrankenEnOfVoedselGaatVerstrekken')
                            ->label('Op hoeveel punten en op welke locaties gaat u dranken en voedsel verstrekken?')
                            ->schema([
                                TextInput::make('naamVanDeLocatie')
                                    ->label('Naam van de locatie')
                                    ->required()
                                    ->maxLength(1000),
                                TextInput::make('uitgiftepuntenVoedsel')
                                    ->label('Uitgiftepunten voedsel')
                                    ->numeric()
                                    ->required(),
                                TextInput::make('uitgiftepuntenDrank')
                                    ->label('Uitgiftepunten drank')
                                    ->numeric()
                                    ->required(),
                                TextInput::make('waarvanMetAlcohol')
                                    ->label('Waarvan met alcohol')
                                    ->numeric()
                                    ->required()
                                    ->hidden(fn (Get $get): bool => $get('watZijnDeLocatiesWaarUDrankenEnOfVoedselGaatVerstrekken.uitgiftepuntenDrank') === '0'),
                            ]),
                    ])
                    ->hidden(),
                Fieldset::make('Eten bereiden of verkopen')
                    ->schema([
                        Placeholder::make('content18')
                            ->content(new HtmlString('<p>U heeft aangegeven, dat er sprake is van eten bereiden of verkopen. Hieronder volgen een aantal vragen hierover.</p>')),
                        Radio::make('welkSoortBereidingVanEtenswarenIsVanToepassingOpLocatieEvenementX')
                            ->label('Welk soort bereiding van etenswaren is van toepassing op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}?')
                            ->options([
                                'beperkteBereiding' => 'Beperkte bereiding',
                                'eenvoudigeBereiding' => 'Eenvoudige bereiding',
                                'uitgebreideBereiding' => 'Uitgebreide bereiding',
                            ])
                            ->required(),
                        Radio::make('maaktUGebruikVanEenCateraarSOpLocatieEvenementX')
                            ->label('Maakt u gebruik van een cateraar(s) op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}?')
                            ->required(),
                        CheckboxList::make('metWelkeWarmtebronWordtHetEtenTerPlaatseKlaargemaaktOpLocatieEvenementX')
                            ->label('Met welke warmtebron wordt het eten ter plaatse klaargemaakt  op locatie Evenement {{ watIsDeNaamVanHetEvenementVergunning }}?')
                            ->options([
                                'gas' => 'Gas',
                                'houtskoolbarbecueOfHoutoven' => 'Houtskoolbarbecue of houtoven',
                                'elektrisch' => 'Elektrisch',
                                'frituur' => 'Frituur',
                                'anders' => 'Anders',
                            ])
                            ->required()
                            ->hidden()
                            ->live(),
                        Textarea::make('welkeAndereWarmtebronWordtGebruikt')
                            ->label('Welke andere warmtebron wordt gebruikt?')
                            ->required()
                            ->maxLength(10000)
                            ->visible(fn (Get $get): bool => $get('metWelkeWarmtebronWordtHetEtenTerPlaatseKlaargemaaktOpLocatieEvenementX.anders') === true),
                    ])
                    ->hidden(),
                Fieldset::make('Belemmering van verkeer')
                    ->schema([
                        Placeholder::make('content19')
                            ->content(new HtmlString('<p>U heeft aangegeven, dat er sprake is van belemmering van verkeer. Hieronder volgen een aantal vragen hierover.</p>')),
                        Textarea::make('beschrijfOpWelkeWijzeErSprakeIsVanBelemmeringVanVerkeer')
                            ->label('Beschrijf op welke wijze er sprake is van belemmering van verkeer')
                            ->required()
                            ->maxLength(10000),
                    ])
                    ->hidden(),
                Fieldset::make('Weg of vaarweg afsluiten')
                    ->schema([
                        Placeholder::make('content20')
                            ->content(new HtmlString('<p>U heeft aangegeven, dat u een (deel van-) de doorgaande weg of vaarweg wilt afsluiten voor doorgaand verkeer. Hieronder volgen een aantal vragen hierover.</p>')),
                        Repeater::make('welkeDoorgangenWiltUAfsluiten')
                            ->label('Welke doorgangen wilt u afsluiten?')
                            ->schema([
                                Map::make('positieVanDeDoorgang')
                                    ->label('Positie van de doorgang')
                                    ->required(),
                                TextInput::make('naamVanDeDoorgang')
                                    ->label('Naam van de doorgang')
                                    ->required()
                                    ->maxLength(1000),
                                DateTimePicker::make('startVanDeAfsluiting')
                                    ->label('Start van de afsluiting')
                                    ->required(),
                                DateTimePicker::make('eindVanDeAfsluiting')
                                    ->label('Eind van de afsluiting')
                                    ->required(),
                            ]),
                    ])
                    ->hidden(),
                Fieldset::make('Toegang voor hulpdiensten is beperkt')
                    ->schema([
                        Placeholder::make('content21')
                            ->content(new HtmlString('<p><strong>Beschrijf in de bijlage “Verkeersplan” de calamiteitenroute en op welke wijze de toegang voor hulpdiensten beperkt wordt en welke maatregelen u heeft getroffen om de deze beperking op te lossen.</strong></p>')),
                        TextInput::make('watIsDeNaamVanDeFunctionarisOfPersoonDieDeTaakHeeftOmInGevalVanEenCalamiteitDeHulpdienstenOpTeVangen')
                            ->label('Wat is de naam van de functionaris of persoon die de taak heeft om in geval van een calamiteit de hulpdiensten op te vangen?')
                            ->required()
                            ->maxLength(1000),
                        TextInput::make('watIsHetTelefoonnummerVanDeFunctionarisOfPersoonDieDeTaakHeeftOmInGevalVanEenCalamiteitDeHulpdienstenOpTeVangen')
                            ->label('Wat is het telefoonnummer van de functionaris of persoon die de taak heeft om in geval van een calamiteit de hulpdiensten op te vangen?')
                            ->tel()
                            ->required(),
                        Textarea::make('vermeldWaarBinnenOfBijHetEvenemententerreinDeHulpdienstenWordenOpgevangenInGevalVanEenCalamiteit')
                            ->label('Vermeld waar binnen of bij het evenemententerrein de hulpdiensten worden opgevangen in geval van een calamiteit.')
                            ->required()
                            ->maxLength(10000),
                    ])
                    ->hidden(),
            ]);
    }
}
