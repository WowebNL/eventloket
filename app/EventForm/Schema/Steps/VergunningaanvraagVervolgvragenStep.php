<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use App\EventForm\Components\InfoText;
use App\EventForm\Components\JaNeeOptions;
use App\EventForm\Schema\Hidden;
use App\EventForm\Schema\Label;
use Carbon\Carbon;
use Dotswan\MapPicker\Fields\Map;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;

/**
 * @openforms-step-uuid 661aabb7-e927-4a75-8d95-0a665c5d83fe
 *
 * @openforms-step-index 9
 */
final class VergunningaanvraagVervolgvragenStep
{
    public const UUID = '661aabb7-e927-4a75-8d95-0a665c5d83fe';

    public static function make(): Step
    {
        return Step::make('Vergunningaanvraag: kenmerken')
            ->key(self::UUID)
            ->schema([
                Fieldset::make('Versterkte muziek')
                    ->columns(1)
                    ->schema([
                        InfoText::info('content5', '<p>U heeft aangegeven, dat er sprake is van versterkte muziek. Hieronder volgen een aantal vragen hierover.</p>'),
                        CheckboxList::make('wieMaaktDeMuziekOpLocatieBijUwEvenementWatIsDeNaamVanHetEvenementVergunning')
                            ->label(Label::render('Wie maakt de muziek op locatie bij uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?'))
                            ->options([
                                'dj' => 'DJ',
                                'band' => 'Band',
                                'orkest' => 'Orkest',
                                'tapeArtiest' => '(Tape-)artiest',
                                'anders' => 'Anders',
                            ])
                            ->required()
                            ->hidden(Hidden::rule('wieMaaktDeMuziekOpLocatieBijUwEvenementWatIsDeNaamVanHetEvenementVergunning'))
                            ->live(),
                        Textarea::make('opWelkeAndereManierWordtErMuziekGemaakt')
                            ->label('Op welke andere manier wordt er muziek gemaakt?')
                            ->required()
                            ->maxLength(10000)
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('opWelkeAndereManierWordtErMuziekGemaakt');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! (in_array('anders', (array) $get('wieMaaktDeMuziekOpLocatieBijUwEvenementWatIsDeNaamVanHetEvenementVergunning'), true));
                            }),
                        CheckboxList::make('welkeSoortenMuziekZijnErTeHorenOpLocatieEvenementX')
                            ->label(Label::render('Welke soorten muziek zijn er te horen op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}?'))
                            ->options([
                                'A69' => 'Klassiek',
                                'A70' => 'Jazz',
                                'A71' => 'Dance',
                                'A72' => 'Pop (en overige)',
                            ])
                            ->required()
                            ->hidden(Hidden::rule('welkeSoortenMuziekZijnErTeHorenOpLocatieEvenementX'))
                            ->live(),
                        CheckboxList::make('welkeSoortenDanceMuziekZijnErTeHorenOpLocatieEvenementX')
                            ->label(Label::render('Welke soorten Dance muziek zijn er te horen op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}?'))
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
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('welkeSoortenDanceMuziekZijnErTeHorenOpLocatieEvenementX');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! (in_array('A71', (array) $get('welkeSoortenMuziekZijnErTeHorenOpLocatieEvenementX'), true));
                            }),
                        CheckboxList::make('welkeSoortenPopmuziekZijnErTeHorenOpLocatieEvenement')
                            ->label(Label::render('Welke soorten popmuziek zijn er te horen op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}?'))
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
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('welkeSoortenPopmuziekZijnErTeHorenOpLocatieEvenement');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! (in_array('A72', (array) $get('welkeSoortenMuziekZijnErTeHorenOpLocatieEvenementX'), true));
                            })
                            ->live(),
                        Textarea::make('welkeAnderSoortPopmuziekIsErTeHorenOpEvenementX')
                            ->label(Label::render('Welke ander soort popmuziek is er te horen op evenement {{ watIsDeNaamVanHetEvenementVergunning }}?'))
                            ->required()
                            ->maxLength(10000)
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('welkeAnderSoortPopmuziekIsErTeHorenOpEvenementX');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! (in_array('anders', (array) $get('welkeSoortenPopmuziekZijnErTeHorenOpLocatieEvenement'), true));
                            }),
                        TextInput::make('watIsDeGeluidsbelastingInDecibelDBANorm0103DBVanUwEvenementX')
                            ->label(Label::render('Wat is de geluidsbelasting in decibel (dB(A) norm - (0–103 dB)) van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?'))
                            ->numeric()
                            ->required()
                            ->belowContent([
                                Icon::make(Heroicon::InformationCircle),
                                'Deze geluidssterkte moet gemeten worden op de zogenaamde "Front of House" afstand van maximaal 25 meter. De dB(A) norm meet het geluidsniveau zoals het menselijk oor dat waarneemt. Het kan gemeten worden met een geluidsniveaumeter, die een A-filter toepast. Bij festivals wordt dat gemeten op 25 meter vanaf het podium.',
                            ]),
                        TextInput::make('watIsDeGeluidsbelastingInDecibelDBCNorm0103DBVanUwEvenement')
                            ->label(Label::render('Wat is de geluidsbelasting in decibel Db(C) norm - (0–113 dB)) van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?'))
                            ->numeric()
                            ->required()
                            ->belowContent([
                                Icon::make(Heroicon::InformationCircle),
                                'Deze geluidssterkte moet gemeten worden op de zogenaamde "Front of House" afstand van maximaal 25 meter. De dB(C) norm meet het geluidsniveau, waarbij verhoudingsgewijs meer rekening gehouden wordt met de bas-tonen, zoals bijv. bij pop- of dance-evenementen. Het kan gemeten worden met een geluidsniveaumeter, die een C-filter toepast. Bij festivals wordt dat gemeten op 25 meter vanaf het podium.',
                            ]),
                    ])
                    ->hidden(Hidden::rule('versterkteMuziek')),
                Fieldset::make('Bouwsels > 10m²')
                    ->columns(1)
                    ->schema([
                        InfoText::info('content15', '<p>U heeft aangegeven, dat er bouwsels > 10m² zoals tenten of podia geplaatst worden. Hieronder volgen een aantal vragen hierover.</p>'),
                        CheckboxList::make('watVoorBouwselsPlaatsUOpDeLocaties')
                            ->label('Wat voor bouwsels plaats u op de locaties?')
                            ->options([
                                'A54' => 'Tent(en)',
                                'A55' => 'Podia',
                                'A56' => 'Overkappingen',
                                'A57' => 'Omheiningen',
                            ])
                            ->required()
                            ->hidden(Hidden::rule('watVoorBouwselsPlaatsUOpDeLocaties'))
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
                            ->hidden(Hidden::rule('tenten')),
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
                            ->hidden(Hidden::rule('podia')),
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
                            ->hidden(Hidden::rule('overkappingen')),
                        Textarea::make('geefEenOmschrijvingVanSoortOmheining')
                            ->label('Geef een omschrijving van soort omheining')
                            ->required()
                            ->maxLength(10000)
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('geefEenOmschrijvingVanSoortOmheining');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! (in_array('A57', (array) $get('watVoorBouwselsPlaatsUOpDeLocaties'), true));
                            }),
                        FileUpload::make('plaatstUTijdelijkeConstructiesTentenPodiaEtcDanDientUNaastHetVeiligheidsplanTevensEenDeelplanTijdelijkeConstructiesTeMakenEnTeUploadenAlsBijlage')
                            ->label('Plaatst u tijdelijke constructies (tenten, podia etc.) dan dient u naast het veiligheidsplan tevens een \'Deelplan Tijdelijke constructies\' te maken en te uploaden als bijlage.')
                            ->belowContent([
                                Icon::make(Heroicon::InformationCircle),
                                'In het "Deelplan tijdelijke constructies" zijn de opbouw-, afbreek- en constructiedetails voor constructies als tenten, podia, overkappingen en tribunes beschreven.',
                            ]),
                    ])
                    ->hidden(Hidden::rule('bouwsels10MSup2Sup')),
                Fieldset::make('Kansspelen')
                    ->columns(1)
                    ->schema([
                        InfoText::info('content16', '<p>U heeft kansspelen aangekruist.&nbsp;Hieronder volgen een aantal vragen daarover.</p>'),
                        Textarea::make('welkSoortKansspelBetreftHet')
                            ->label('Welk soort kansspel betreft het?')
                            ->required()
                            ->maxLength(10000),
                        Radio::make('isDeOrganisatieVanHetKansspelInHandenVanEenVereniging')
                            ->label('Is de organisatie van het kansspel in handen van een vereniging?')
                            ->options(JaNeeOptions::OPTIONS)
                            ->required()
                            ->live(),
                        InfoText::info('content16_2', '<p>De behandelaar van de gemeente zal u informeren wanneer de gemeente bijzondere richtlijnen hanteert voor het organiseren van kansspelen.</p>'),
                    ])
                    ->hidden(Hidden::rule('kansspelen')),
                Fieldset::make('Alcoholische dranken')
                    ->columns(1)
                    ->schema([
                        InfoText::info('content17', '<p>U heeft aangegeven, dat er alcoholische dranken genuttigd worden. Hieronder volgt een aantal vragen hierover.</p>'),
                        Radio::make('isEenPersoonOfOrganisatieVerantwoordelijkVoorDeAlcoholverkoop')
                            ->label('Is een persoon of organisatie verantwoordelijk voor de alcoholverkoop?')
                            ->options([
                                'persoon' => 'Persoon',
                                'organisatie' => 'Organisatie',
                            ])
                            ->required()
                            ->live(),
                        Fieldset::make('Persoongroep')
                            ->columns(1)
                            ->schema([
                                InfoText::info('content17_2', '<p>vul de persoonlijke gegevens van degene onder wiens onmiddelijke leiding de verstrekking van zwak alcoholische (<15%) zal plaatsvinden</p>'),
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
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (?string $state, callable $set): void {
                                        $bereikt = $state ? Carbon::parse($state)->age >= 21 : null;
                                        $set('heeftDeLeeftijdVan21JaarBereiktPersoonAlcohol', match ($bereikt) {
                                            true => 'Ja',
                                            false => 'Nee',
                                            default => null,
                                        });
                                    }),
                                TextInput::make('geboorteplaatsPersoonAlcohol')
                                    ->label('Geboorteplaats persoon')
                                    ->required()
                                    ->maxLength(1000),
                                TextInput::make('straatEnHuisnummerPersoonAlcohol')
                                    ->label('Straat en huisnummer')
                                    ->required()
                                    ->maxLength(1000),
                                TextInput::make('postcodePersoonAlcohol')
                                    ->label('Postcode')
                                    ->required()
                                    ->maxLength(10),
                                TextInput::make('woonplaatsPersoonAlcohol')
                                    ->label('Woonplaats')
                                    ->required()
                                    ->maxLength(1000),
                                TextInput::make('telefoonnummerPersoonAlcohol')
                                    ->label('Telefoonnummer')
                                    ->tel()
                                    ->required(),
                                Radio::make('heeftDeLeeftijdVan21JaarBereiktPersoonAlcohol')
                                    ->label('Heeft de leeftijd van 21 jaar bereikt?')
                                    ->options(JaNeeOptions::OPTIONS)
                                    ->disabled()
                                    ->dehydrated(),
                                Radio::make('isNietInEnigOpzichtVanSlechtLevensgedragPersoonAlcohol')
                                    ->label('Is niet in enig opzicht van slecht levensgedrag?')
                                    ->options(JaNeeOptions::OPTIONS)
                                    ->required(),
                                Radio::make('deAaneengeslotePeriodeVoorHetVerstrekkenVanDrankIsNietMeerDan12AangeslotenDagen')
                                    ->label('De aaneengesloten periode voor het verstrekken van drank is niet meer dan 12 aaneengesloten dagen?')
                                    ->options(JaNeeOptions::OPTIONS)
                                    ->required(),
                            ])
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('persoongroep');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! ($get('isEenPersoonOfOrganisatieVerantwoordelijkVoorDeAlcoholverkoop') === 'persoon');
                            }),
                        Fieldset::make('Organisatiegroep')
                            ->columns(1)
                            ->schema([
                                TextInput::make('watIsDeNaamVanDeOrganisatie')
                                    ->label('Wat is de naam van de organisatie?')
                                    ->required()
                                    ->maxLength(1000),
                                TextInput::make('watIsHetTelefoonnummerVanDeOrganisatie')
                                    ->label('Wat is het telefoonnummer van de organisatie?')
                                    ->tel()
                                    ->required(),
                                InfoText::info('content17_3', '<p> De organisatie dient zelf een alcoholontheffing aan te vragen voor dit evenement.</p>'),
                            ])
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('organisatiegroep');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! ($get('isEenPersoonOfOrganisatieVerantwoordelijkVoorDeAlcoholverkoop') === 'organisatie');
                            }),
                        Repeater::make('watZijnDeLocatiesWaarUDrankenEnOfVoedselGaatVerstrekken')
                            ->label('Op hoeveel punten en op welke locaties gaat u dranken en voedsel verstrekken?')
                            ->schema([
                                TextInput::make('naamVanDeLocatie')
                                    ->label('Naam van de locatie')
                                    ->required()
                                    ->belowContent([
                                        Icon::make(Heroicon::InformationCircle),
                                        'Geef de naam/omschrijving van de locatie',
                                    ])
                                    ->maxLength(1000),
                                TextInput::make('uitgiftepuntenVoedsel')
                                    ->label('Uitgiftepunten voedsel')
                                    ->numeric()
                                    ->belowContent([
                                        Icon::make(Heroicon::InformationCircle),
                                        'vul hier het aantal in',
                                    ])
                                    ->required(),
                                TextInput::make('uitgiftepuntenDrank')
                                    ->label('Uitgiftepunten drank')
                                    ->numeric()
                                    ->belowContent([
                                        Icon::make(Heroicon::InformationCircle),
                                        'vul hier het aantal in',
                                    ])
                                    ->required(),
                                TextInput::make('waarvanMetAlcohol')
                                    ->label('Waarvan met alcohol')
                                    ->numeric()
                                    ->belowContent([
                                        Icon::make(Heroicon::InformationCircle),
                                        'vul hier het aantal in',
                                    ])
                                    ->required()
                                    ->hidden(function (Get $get, $livewire): bool {
                                        $rule = $livewire->state()->isFieldHidden('waarvanMetAlcohol');
                                        if ($rule !== null) {
                                            return $rule;
                                        }

                                        return $get('watZijnDeLocatiesWaarUDrankenEnOfVoedselGaatVerstrekken.uitgiftepuntenDrank') === '0';
                                    }),
                            ]),
                    ])
                    ->hidden(Hidden::rule('alcoholischeDranken')),
                Fieldset::make('Eten bereiden of verkopen')
                    ->columns(1)
                    ->schema([
                        InfoText::info('content18', '<p>U heeft aangegeven, dat er sprake is van eten bereiden of verkopen. Hieronder volgt een aantal vragen hierover.</p>'),
                        Radio::make('welkSoortBereidingVanEtenswarenIsVanToepassingOpLocatieEvenementX')
                            ->label(Label::render('Welk soort bereiding van etenswaren is van toepassing op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}?'))
                            ->options([
                                'beperkteBereiding' => 'Beperkte bereiding',
                                'eenvoudigeBereiding' => 'Eenvoudige bereiding',
                                'uitgebreideBereiding' => 'Uitgebreide bereiding',
                            ])
                            ->descriptions([
                                'beperkteBereiding' => 'Bijv. een broodje kaas/vlees',
                                'eenvoudigeBereiding' => 'Bijv. frituren van friet/snacks',
                                'uitgebreideBereiding' => 'Volledige maaltijd bereiding',
                            ])
                            ->required(),
                        Radio::make('maaktUGebruikVanEenCateraarSOpLocatieEvenementX')
                            ->label(Label::render('Maakt u gebruik van een cateraar(s) op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}?'))
                            ->options(JaNeeOptions::OPTIONS)
                            ->required(),
                        CheckboxList::make('metWelkeWarmtebronWordtHetEtenTerPlaatseKlaargemaaktOpLocatieEvenementX')
                            ->label(Label::render('Met welke warmtebron wordt het eten ter plaatse klaargemaakt  op locatie Evenement {{ watIsDeNaamVanHetEvenementVergunning }}?'))
                            ->options([
                                'gas' => 'Gas',
                                'houtskoolbarbecueOfHoutoven' => 'Houtskoolbarbecue of houtoven',
                                'elektrisch' => 'Elektrisch',
                                'frituur' => 'Frituur',
                                'anders' => 'Anders',
                            ])
                            ->descriptions([
                                'gas' => 'bijvoorbeeld kookplaat, gasoven of gasbarbecue',
                                'elektrisch' => 'bijvoorbeeld kookplaat, elektrische oven of magnetron',
                            ])
                            ->required()
                            ->belowContent([
                                Icon::make(Heroicon::InformationCircle),
                                'Houdt rekening met de BGBOP wetgeving, die een afstand van 2 meter tot warmtebronnen tot gebouwen of objecten voorschrijft. Voor frituren is dat 5 meter.',
                            ])
                            ->hidden(Hidden::rule('metWelkeWarmtebronWordtHetEtenTerPlaatseKlaargemaaktOpLocatieEvenementX'))
                            ->live(),
                        Textarea::make('welkeAndereWarmtebronWordtGebruikt')
                            ->label('Welke andere warmtebron wordt gebruikt?')
                            ->required()
                            ->maxLength(10000)
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('welkeAndereWarmtebronWordtGebruikt');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! (in_array('anders', (array) $get('metWelkeWarmtebronWordtHetEtenTerPlaatseKlaargemaaktOpLocatieEvenementX'), true));
                            }),
                    ])
                    ->hidden(Hidden::rule('etenBereidenOfVerkopen')),
                Fieldset::make('Belemmering van verkeer')
                    ->columns(1)
                    ->schema([
                        InfoText::info('content19', '<p>U heeft aangegeven, dat er sprake is van belemmering van verkeer. Hieronder volgt een aantal vragen hierover.</p>'),
                        Textarea::make('beschrijfOpWelkeWijzeErSprakeIsVanBelemmeringVanVerkeer')
                            ->label('Beschrijf op welke wijze er sprake is van belemmering van verkeer')
                            ->required()
                            ->maxLength(10000),
                    ])
                    ->hidden(Hidden::rule('belemmeringVanVerkeer')),
                Fieldset::make('Weg of vaarweg afsluiten')
                    ->columns(1)
                    ->schema([
                        InfoText::info('content20', '<p>U heeft aangegeven, dat u een (deel van-) de doorgaande weg of vaarweg wilt afsluiten voor doorgaand verkeer. Hieronder volgt een aantal vragen hierover.</p>'),
                        Repeater::make('welkeDoorgangenWiltUAfsluiten')
                            ->label('Welke doorgangen wilt u afsluiten?')
                            ->schema([
                                Map::make('positieVanDeDoorgang')
                                    ->label('Positie van de doorgang')
                                    ->defaultLocation(50.8514, 5.6910)
                                    ->zoom(11)
                                    ->geoMan(true)
                                    ->geoManEditable(true)
                                    ->drawPolygon(false)
                                    ->drawPolyline(false)
                                    ->drawMarker(true)
                                    ->drawCircle(false)
                                    ->drawCircleMarker(false)
                                    ->drawRectangle(false)
                                    ->cutPolygon(false)
                                    ->dragMode(false)
                                    ->rotateMode(false)
                                    ->showMarker(false)
                                    ->extraStyles(['min-height: 25rem', 'border-radius: 0.5rem'])
                                    ->columnSpanFull()
                                    ->required(),
                                TextInput::make('naamVanDeDoorgang')
                                    ->label('Naam van de doorgang')
                                    ->required()
                                    ->maxLength(1000),
                                DateTimePicker::make('startVanDeAfsluiting')
                                    ->label('Start van de afsluiting')
                                    ->seconds(false)
                                    ->required(),
                                DateTimePicker::make('eindVanDeAfsluiting')
                                    ->label('Eind van de afsluiting')
                                    ->seconds(false)
                                    ->required(),
                            ]),
                    ])
                    ->hidden(Hidden::rule('wegOfVaarwegAfsluiten')),
                Fieldset::make('Toegang voor hulpdiensten is beperkt')
                    ->columns(1)
                    ->schema([
                        InfoText::warning('content21', '<p><strong>Beschrijf in de bijlage “Verkeersplan” de calamiteitenroute en op welke wijze de toegang voor hulpdiensten beperkt wordt en welke maatregelen u heeft getroffen om deze beperking op te lossen.</strong></p>'),
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
                    ->hidden(Hidden::rule('toegangVoorHulpdienstenIsBeperkt')),
            ]);
    }
}
