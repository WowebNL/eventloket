<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use App\EventForm\Components\AddressNL;
use App\EventForm\Components\InfoText;
use App\EventForm\Schema\Hidden;
use App\EventForm\Schema\Label;
use App\EventForm\State\FormState;
use Dotswan\MapPicker\Fields\Map;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Exceptions\Halt;

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
            ->afterValidation(function ($livewire): void {
                $evenementInGemeente = $livewire->state()->get('evenementInGemeente');

                if (empty($evenementInGemeente)) {
                    Notification::make()
                        ->title('Gemeente niet bepaald')
                        ->body('Vul een adres, locatie of route in zodat de gemeente automatisch bepaald kan worden voordat u verder gaat.')
                        ->warning()
                        ->send();

                    throw new Halt;
                }
            })
            ->schema([
                CheckboxList::make('waarVindtHetEvenementPlaats')
                    ->label(Label::render('Waar vindt het evenement {{ watIsDeNaamVanHetEvenementVergunning }} plaats?'))
                    ->options([
                        'gebouw' => 'In een gebouw of meerdere gebouwen',
                        'buiten' => 'Buiten op één of meerdere plaatsen',
                        'route' => 'Op een route',
                    ])
                    ->required()
                    ->live(),
                Repeater::make('adresVanDeGebouwEn')
                    ->label('Adres van de gebouw(en)')
                    ->addActionLabel('Adres toevoegen')
                    ->schema([
                        TextInput::make('naamVanDeLocatieGebouw')
                            ->label('Naam van de locatie')
                            ->required()
                            ->maxLength(1000),
                        AddressNL::make('adresVanHetGebouwWaarUwEvenementPlaatsvindt1', 'Adres van het gebouw waar uw evenement plaatsvindt.'),
                    ])
                    ->hidden(Hidden::rule('adresVanDeGebouwEn')),
                Repeater::make('locatieSOpKaart')
                    ->label('Locatie(s) op kaart')
                    ->schema([
                        TextInput::make('naamVanDeLocatieKaart')
                            ->label('Naam van de locatie')
                            ->required()
                            ->maxLength(1000),
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
                            ->drawCircleMarker(false)
                            ->drawRectangle(false)
                            ->cutPolygon(false)
                            ->dragMode(false)
                            ->rotateMode(false)
                            ->showMarker(false)
                            ->extraStyles(['min-height: 25rem', 'border-radius: 0.5rem'])
                            ->columnSpanFull()
                            ->required(),
                    ])
                    ->hidden(Hidden::rule('locatieSOpKaart')),
                Fieldset::make('Route')
                    ->schema([
                        InfoText::info('infoGpx1', '<p>Wanneer het een eenvoudige route betreft (bijvoorbeeld voor een processie), dan kun je hieronder de route intekenen op de kaart.</p><p>Ingeval het een complexe route betreft (bijvoorbeeld een wielertocht), dan wordt aanbevolen om de route op de kaart globaal in te tekenen, zodat de applicatie kan herkennen door welke gemeenten de route gaat (en deze daarover informeren). Voor de detailroute bieden we hieronder de mogelijkheid voor het uploaden van een GPX bestand.</p>'),
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
                                    ->drawCircleMarker(false)
                                    ->drawRectangle(false)
                                    ->cutPolygon(false)
                                    ->dragMode(false)
                                    ->rotateMode(false)
                                    ->showMarker(false)
                                    ->extraStyles(['min-height: 25rem', 'border-radius: 0.5rem'])
                                    ->columnSpanFull()
                                    ->required(),
                            ]),
                        FileUpload::make('gpxBestandVanDeRoute')
                            ->label('GPX bestand van de route'),
                        TextInput::make('naamVanDeRoute')
                            ->label('Naam van de route')
                            ->required()
                            ->maxLength(1000)
                            // Direct per keystroke synchroniseren naar
                            // Livewire-state. Nodig omdat de gemeente-
                            // response na route-tekenen een form-rerender
                            // triggert; debounce (500ms) en onBlur lieten
                            // beide een race-window open waarin de net
                            // getypte naam alsnog werd platgewalst.
                            ->live(),
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
                            ->live(),
                        Textarea::make('welkSoortRouteEvenementBetreftUwEvenementX')
                            ->label('Welk soort evenement vindt plaats op de route?')
                            ->required()
                            ->maxLength(10000)
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('welkSoortRouteEvenementBetreftUwEvenementX');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! ($get('watVoorEvenementGaatPlaatsvindenOpDeRoute1') === 'A114');
                            }),
                        CheckboxList::make('komtUwRouteOverWegenVanWegbeheerdersAndersDanDeBetreffendeGemeenteZoJaKruisDezeDanAan')
                            ->label('Komt uw route over wegen van wegbeheerders, anders dan de betreffende gemeente? Zo ja, kruis deze dan aan.')
                            ->options([
                                'provincie' => 'Provincie',
                                'waterschap' => 'Waterschap',
                                'rijkswaterstaat' => 'Rijkswaterstaat',
                                'staatsbosbeheer' => 'Staatsbosbeheer',
                            ])
                            ->live(),
                        InfoText::info('content1', '<p>Voor het gebruik van provinciale wegen, of in het geval van een wegwedstrijd die door meerdere gemeenten binnen de provincie voert dient er <a href="https://www.limburg.nl/@1161/wedstrijden-weg" target="_blank" rel="noopener noreferrer">een verzoek voor ontheffing van de openbare weg </a>gericht te worden aan de Provincie Limburg.</p>')
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('content1');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! (in_array('provincie', (array) $get('komtUwRouteOverWegenVanWegbeheerdersAndersDanDeBetreffendeGemeenteZoJaKruisDezeDanAan'), true));
                            }),
                        InfoText::info('content39', '<p>Voor het afgeven van een ontheffing voor het kruisen van wegen/waters van het Waterschap dient u een aanvraag te doen via <a href="https://www.waterschaplimburg.nl/overons/regels-wetgeving-0/melding-vergunning/" target="_blank" rel="noopener noreferrer">de website</a>.</p>')
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('content39');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! (in_array('waterschap', (array) $get('komtUwRouteOverWegenVanWegbeheerdersAndersDanDeBetreffendeGemeenteZoJaKruisDezeDanAan'), true));
                            }),
                        InfoText::info('content41', '<p>Voor het afgeven van een ontheffing voor het kruisen van wegen/waters van het Rijkswaterstaat dient u een aanvraag te doen via <a href="https://www.rijkswaterstaat.nl/wegen/wetten-regels-en-vergunningen/vergunningen-rijkswegen" target="_blank" rel="noopener noreferrer">de website van Rijkswaterstaat</a>.</p>')
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('content41');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! (in_array('rijkswaterstaat', (array) $get('komtUwRouteOverWegenVanWegbeheerdersAndersDanDeBetreffendeGemeenteZoJaKruisDezeDanAan'), true));
                            }),
                        InfoText::info('content40', '<p>Voor het afgeven van een ontheffing voor het kruisen van wegen/paden van het Staatsbosheer dient u een aanvraag te doen via <a href="https://www.staatsbosbeheer.nl/contact/evenementen-aanmelden" target="_blank" rel="noopener noreferrer">de website van Staatsbosbeheer</a>.</p>')
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('content40');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! (in_array('staatsbosbeheer', (array) $get('komtUwRouteOverWegenVanWegbeheerdersAndersDanDeBetreffendeGemeenteZoJaKruisDezeDanAan'), true));
                            }),
                        // Pas tonen zodra `inGemeentenResponse.line` daadwerkelijk
                        // gevuld is (dus na een ingetekende route + lookup van de
                        // doorkruiste gemeenten). De if/elif-cascade die voorheen
                        // in een template-string zat, is nu als gewone PHP-method
                        // — leesbaarder en bevat de gemeente-namen via `e()` zodat
                        // ze veilig in HtmlString belanden.
                        InfoText::info('routeStartEndContent2', fn (FormState $state) => self::renderRouteStatus($state))
                            ->hidden(function ($livewire): bool {
                                $line = $livewire->state()->get('inGemeentenResponse.line');

                                return ! is_array($line) || ! isset($line['start_end_equal']);
                            }),
                    ])
                    ->hidden(function (Get $get, $livewire): bool {
                        $rule = $livewire->state()->isFieldHidden('route');
                        if ($rule !== null) {
                            return $rule;
                        }

                        return ! (in_array('route', (array) $get('waarVindtHetEvenementPlaats'), true));
                    }),
                InfoText::warning('NotWithin', '<h3>Let op</h3><p>Een ingevoerd adres of (een deel van) een getekende route of locatie valt buiten de gemeenten die EventLoket gebruiken.</p><p>Eventloket wordt gebruikt door de gemeenten:&nbsp;{{ alleGemeenteNamen }}</p>')
                    ->hidden(Hidden::rule('NotWithin')),
                Radio::make('userSelectGemeente')
                    ->label('De ingevoerde locatie(s) of route valt binnen of doorkruist meerdere gemeenten, wat is de gemeente waarbinnen u de aanvraag wilt doen?')
                    ->options(fn ($livewire): array => collect((array) $livewire->state()->get('inGemeentenResponse.all.items'))->mapWithKeys(fn ($item) => [(string) ($item['brk_identification'] ?? '') => (string) ($item['name'] ?? '')])->all())
                    ->required()
                    ->hidden(Hidden::rule('userSelectGemeente'))
                    ->live(),
                InfoText::info('contentRouteDoorkuistMeerdereGemeenteInfo', '<p>De ingetekende route doorkruist de volgende gemeente(n): {{ routeDoorGemeentenNamen|join:", " }} &nbsp;U gaat de vergunningaanvraag invullen voor de gemeente&nbsp;<strong> {% get_value evenementInGemeente \'name\' %}</strong>, de overige gemeente(n) die gebruik maken van Eventloket op de route zullen automatisch geïnformeerd worden.</p>')
                    ->hidden(Hidden::rule('contentRouteDoorkuistMeerdereGemeenteInfo')),
                InfoText::info('content200', '<p>U gaat verder met deze aanvraag voor de gemeente:<strong> {% get_value evenementInGemeente \'name\' %}</strong></p>')
                    ->hidden(Hidden::rule('content200')),
            ]);
    }

    /**
     * Statustekst onder de Route-fieldset: één gemeente of twee
     * verschillende. Wordt alleen gerenderd zodra `inGemeentenResponse.line`
     * gevuld is (zie de `->hidden(...)` op de bijbehorende InfoText).
     */
    private static function renderRouteStatus(FormState $state): string
    {
        $line = $state->get('inGemeentenResponse.line');
        if (! is_array($line)) {
            return '';
        }

        $startNaam = (string) ($line['start']['name'] ?? '');
        $eindNaam = (string) ($line['end']['name'] ?? '');

        if (($line['start_end_equal'] ?? null) === true) {
            return '<p>De route start en eindigt binnen de gemeente <strong>'.e($startNaam).'</strong>.</p>';
        }

        return '<p>De route start in de gemeente <strong>'.e($startNaam).'</strong> '
            .'en eindigt in de gemeente <strong>'.e($eindNaam).'</strong>, '
            .'hierdoor kan het zijn dat u bij beide gemeenten een vergunningaanvraag moet doen. '
            .'U vult dit formulier helemaal in voor 1 gemeente; als u de aanvraag vervolgens '
            .'heeft gedaan kunt u binnen de aanvraag in Eventloket de knop "Nieuwe aanvraag" '
            .'gebruiken om een nieuwe aanvraag te starten waarbij (een deel van) het formulier '
            .'al vooraf ingevuld is.</p>';
    }
}
