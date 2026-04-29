<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use App\EventForm\Template\LabelRenderer;
use Dotswan\MapPicker\Fields\Map;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
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
                    ->schema([
                        TextEntry::make('content5')
                            ->hiddenLabel()
                            ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>U heeft aangegeven, dat er sprake is van versterkte muziek. Hieronder volgen een aantal vragen hierover.</p>', $livewire->state()))),
                        CheckboxList::make('wieMaaktDeMuziekOpLocatieBijUwEvenementWatIsDeNaamVanHetEvenementVergunning')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Wie maakt de muziek op locatie bij uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?', $livewire->state()))
                            ->options([
                                'dj' => 'DJ',
                                'band' => 'Band',
                                'orkest' => 'Orkest',
                                'tapeArtiest' => '(Tape-)artiest',
                                'anders' => 'Anders',
                            ])
                            ->required()
                            ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('wieMaaktDeMuziekOpLocatieBijUwEvenementWatIsDeNaamVanHetEvenementVergunning') !== false)
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
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Welke soorten muziek zijn er te horen op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}?', $livewire->state()))
                            ->options([
                                'A69' => 'Klassiek',
                                'A70' => 'Jazz',
                                'A71' => 'Dance',
                                'A72' => 'Pop (en overige)',
                            ])
                            ->required()
                            ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('welkeSoortenMuziekZijnErTeHorenOpLocatieEvenementX') !== false)
                            ->live(),
                        CheckboxList::make('welkeSoortenDanceMuziekZijnErTeHorenOpLocatieEvenementX')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Welke soorten Dance muziek zijn er te horen op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}?', $livewire->state()))
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
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Welke soorten popmuziek zijn er te horen op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}?', $livewire->state()))
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
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Welke ander soort popmuziek is er te horen op evenement {{ watIsDeNaamVanHetEvenementVergunning }}?', $livewire->state()))
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
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Wat is de geluidsbelasting in decibel (dB(A) norm - (0–103 dB)) van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?', $livewire->state()))
                            ->numeric()
                            ->required(),
                        TextInput::make('watIsDeGeluidsbelastingInDecibelDBCNorm0103DBVanUwEvenement')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Wat is de geluidsbelasting in decibel Db(C) norm - (0–113 dB)) van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?', $livewire->state()))
                            ->numeric()
                            ->required(),
                    ])
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('versterkteMuziek') !== false),
                Fieldset::make('Bouwsels &gt; 10m<sup>2</sup> ')
                    ->schema([
                        TextEntry::make('content15')
                            ->hiddenLabel()
                            ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>U heeft aangegeven, dat er bouwsels &gt; 10m2 zoals tenten of podia geplaatst worden. Hieronder volgen een aantal vragen hierover.</p>', $livewire->state()))),
                        CheckboxList::make('watVoorBouwselsPlaatsUOpDeLocaties')
                            ->label('Wat voor bouwsels plaats u op de locaties?')
                            ->options([
                                'A54' => 'Tent(en)',
                                'A55' => 'Podia',
                                'A56' => 'Overkappingen',
                                'A57' => 'Omheiningen',
                            ])
                            ->required()
                            ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('watVoorBouwselsPlaatsUOpDeLocaties') !== false)
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
                            ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('tenten') !== false),
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
                            ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('podia') !== false),
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
                            ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('overkappingen') !== false),
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
                            ->label('Plaatst u tijdelijke constructies (tenten, podia etc.) dan dient u naast het veiligheidsplan tevens een \'Deelplan Tijdelijke constructies\' te maken en te uploaden als bijlage.'),
                    ])
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('bouwsels10MSup2Sup') !== false),
                Fieldset::make('Kansspelen')
                    ->schema([
                        TextEntry::make('content16')
                            ->hiddenLabel()
                            ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>U heeft kansspelen aangekruist.&nbsp;Hieronder volgen een aantal vragen daarover.</p>', $livewire->state()))),
                        Textarea::make('welkSoortKansspelBetreftHet')
                            ->label('Welk soort kansspel betreft het?')
                            ->required()
                            ->maxLength(10000),
                        Radio::make('isDeOrganisatieVanHetKansspelInHandenVanEenVereniging')
                            ->label('Is de organisatie van het kansspel in handen van een vereniging?')
                            ->options([
                                'Ja' => 'Ja',
                                'Nee' => 'Nee',
                            ])
                            ->required()
                            ->live(),
                        Radio::make('bestaatDeVereningingDieHetKansspelOrganiseertLangerDan3Jaar')
                            ->label('Bestaat de vereninging, die het kansspel organiseert langer dan 3 jaar?')
                            ->options([
                                'Ja' => 'Ja',
                                'Nee' => 'Nee',
                            ])
                            ->required()
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('bestaatDeVereningingDieHetKansspelOrganiseertLangerDan3Jaar');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! ($get('isDeOrganisatieVanHetKansspelInHandenVanEenVereniging') === 'Ja');
                            }),
                        Textarea::make('watBentUVanPlanMetDeOpbrengstVanHetKansspelTeGaanDoen')
                            ->label('Wat bent u van plan met de opbrengst van het kansspel te gaan doen?')
                            ->required()
                            ->maxLength(10000),
                        TextInput::make('geefEenIndicatieVanDeHoogteVanHetPrijzengeld')
                            ->label('Geef een indicatie van de hoogte van het prijzengeld')
                            ->numeric()->prefix('€')
                            ->required(),
                    ])
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('kansspelen') !== false),
                Fieldset::make('Alcoholische dranken')
                    ->schema([
                        TextEntry::make('content17')
                            ->hiddenLabel()
                            ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>U heeft aangegeven, dat er alcoholische dranken genuttigd worden. Hieronder volgen een aantal vragen hierover.</p>', $livewire->state()))),
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
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('persoongroep');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! ($get('isEenPersoonOfOrganisatieVerantwoordelijkVoorDeAlcoholverkoop') === 'persoon');
                            }),
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
                                    ->hidden(function (Get $get, $livewire): bool {
                                        $rule = $livewire->state()->isFieldHidden('waarvanMetAlcohol');
                                        if ($rule !== null) {
                                            return $rule;
                                        }

                                        return $get('watZijnDeLocatiesWaarUDrankenEnOfVoedselGaatVerstrekken.uitgiftepuntenDrank') === '0';
                                    }),
                            ]),
                    ])
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('alcoholischeDranken') !== false),
                Fieldset::make('Eten bereiden of verkopen')
                    ->schema([
                        TextEntry::make('content18')
                            ->hiddenLabel()
                            ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>U heeft aangegeven, dat er sprake is van eten bereiden of verkopen. Hieronder volgen een aantal vragen hierover.</p>', $livewire->state()))),
                        Radio::make('welkSoortBereidingVanEtenswarenIsVanToepassingOpLocatieEvenementX')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Welk soort bereiding van etenswaren is van toepassing op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}?', $livewire->state()))
                            ->options([
                                'beperkteBereiding' => 'Beperkte bereiding',
                                'eenvoudigeBereiding' => 'Eenvoudige bereiding',
                                'uitgebreideBereiding' => 'Uitgebreide bereiding',
                            ])
                            ->required(),
                        Radio::make('maaktUGebruikVanEenCateraarSOpLocatieEvenementX')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Maakt u gebruik van een cateraar(s) op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}?', $livewire->state()))
                            ->options([
                                'Ja' => 'Ja',
                                'Nee' => 'Nee',
                            ])
                            ->required(),
                        CheckboxList::make('metWelkeWarmtebronWordtHetEtenTerPlaatseKlaargemaaktOpLocatieEvenementX')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Met welke warmtebron wordt het eten ter plaatse klaargemaakt  op locatie Evenement {{ watIsDeNaamVanHetEvenementVergunning }}?', $livewire->state()))
                            ->options([
                                'gas' => 'Gas',
                                'houtskoolbarbecueOfHoutoven' => 'Houtskoolbarbecue of houtoven',
                                'elektrisch' => 'Elektrisch',
                                'frituur' => 'Frituur',
                                'anders' => 'Anders',
                            ])
                            ->required()
                            ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('metWelkeWarmtebronWordtHetEtenTerPlaatseKlaargemaaktOpLocatieEvenementX') !== false)
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
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('etenBereidenOfVerkopen') !== false),
                Fieldset::make('Belemmering van verkeer')
                    ->schema([
                        TextEntry::make('content19')
                            ->hiddenLabel()
                            ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>U heeft aangegeven, dat er sprake is van belemmering van verkeer. Hieronder volgen een aantal vragen hierover.</p>', $livewire->state()))),
                        Textarea::make('beschrijfOpWelkeWijzeErSprakeIsVanBelemmeringVanVerkeer')
                            ->label('Beschrijf op welke wijze er sprake is van belemmering van verkeer')
                            ->required()
                            ->maxLength(10000),
                    ])
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('belemmeringVanVerkeer') !== false),
                Fieldset::make('Weg of vaarweg afsluiten')
                    ->schema([
                        TextEntry::make('content20')
                            ->hiddenLabel()
                            ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>U heeft aangegeven, dat u een (deel van-) de doorgaande weg of vaarweg wilt afsluiten voor doorgaand verkeer. Hieronder volgen een aantal vragen hierover.</p>', $livewire->state()))),
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
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('wegOfVaarwegAfsluiten') !== false),
                Fieldset::make('Toegang voor hulpdiensten is beperkt')
                    ->schema([
                        TextEntry::make('content21')
                            ->hiddenLabel()
                            ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p><strong>Beschrijf in de bijlage “Verkeersplan” de calamiteitenroute en op welke wijze de toegang voor hulpdiensten beperkt wordt en welke maatregelen u heeft getroffen om de deze beperking op te lossen.</strong></p>', $livewire->state()))),
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
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('toegangVoorHulpdienstenIsBeperkt') !== false),
            ]);
    }
}
