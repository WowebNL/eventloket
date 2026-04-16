<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use Dotswan\MapPicker\Fields\Map;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\HtmlString;

/**
 * @openforms-step-uuid 2186344f-9821-45d1-bd52-9900ae15fcb6
 *
 * @openforms-step-index 2
 */
final class LocatieVanHetEvenement2Step
{
    public static function make(): Step
    {
        return Step::make('Locatie')
            ->schema([
                CheckboxList::make('waarVindtHetEvenementPlaats')
                    ->label('Waar vindt het evenement {{ watIsDeNaamVanHetEvenementVergunning }} plaats?')
                    ->options([
                        'gebouw' => 'In een gebouw of meerdere gebouwen',
                        'buiten' => 'Buiten op één of meerdere plaatsen',
                        'route' => 'Op een route',
                    ])
                    ->required(),
                Fieldset::make('In een gebouw of meerdere gebouwen')
                    ->schema([

                    ]),
                Repeater::make('adresVanDeGebouwEn')
                    ->label('Adres van de gebouw(en)')
                    ->schema([
                        TextInput::make('naamVanDeLocatieGebouw')
                            ->label('Naam van de locatie')
                            ->required()
                            ->maxLength(1000),
                        TextInput::make('adresVanHetGebouwWaarUwEvenementPlaatsvindt1')
                            ->label('Adres van het gebouw waar uw evenement plaatsvindt.')
                            ->placeholder('Postcode + huisnummer')
                            ->required(),
                    ]),
                Fieldset::make('Buiten op één of meerdere plaatsen')
                    ->schema([

                    ]),
                Repeater::make('locatieSOpKaart')
                    ->label('Locatie(s) op kaart')
                    ->schema([
                        TextInput::make('naamVanDeLocatieKaart')
                            ->label('Naam van de locatie')
                            ->required()
                            ->maxLength(1000),
                        Map::make('buitenLocatieVanHetEvenement')
                            ->label('Buiten locatie van het evenement')
                            ->required(),
                    ]),
                Fieldset::make('Route')
                    ->schema([
                        Placeholder::make('infoGpx1')
                            ->content(new HtmlString('<p>Wanneer het een eenvoudige route betreft (bijvoorbeeld voor een processie), dan kun je hieronder de route intekenen op de kaart.</p><p>Ingeval het een complexe route betreft (bijvoorbeeld een wielertocht), dan wordt aanbevolen om de route op de kaart globaal in te tekenen, zodat de applicatie kan herkennen door welke gemeenten de route gaat (en deze daarover informeren). Voor de detailroute bieden we hieronder de mogelijkheid voor het uploaden van een GPX bestand.</p>')),
                        Repeater::make('routesOpKaart')
                            ->label('Route op kaart')
                            ->schema([
                                Map::make('routeVanHetEvenement')
                                    ->label('Route van het evenement')
                                    ->required(),
                            ]),
                        FileUpload::make('gpxBestandVanDeRoute')
                            ->label('GPX bestand van de route'),
                        TextInput::make('naamVanDeRoute')
                            ->label('Naam van de route')
                            ->required()
                            ->maxLength(1000),
                        Select::make('watVoorEvenementGaatPlaatsvindenOpDeRoute1')
                            ->label('Wat voor evenement gaat plaatsvinden op de route?')
                            ->required(),
                        Textarea::make('welkSoortRouteEvenementBetreftUwEvenementX')
                            ->label('Welk soort evenement vindt plaats op de route?')
                            ->required()
                            ->maxLength(10000),
                        CheckboxList::make('komtUwRouteOverWegenVanWegbeheerdersAndersDanDeBetreffendeGemeenteZoJaKruisDezeDanAan')
                            ->label('Komt uw route over wegen van wegbeheerders, anders dan de betreffende gemeente? Zo ja, kruis deze dan aan.')
                            ->options([
                                'provincie' => 'Provincie',
                                'waterschap' => 'Waterschap',
                                'rijkswaterstaat' => 'Rijkswaterstaat',
                                'staatsbosbeheer' => 'Staatsbosbeheer',
                            ]),
                        Placeholder::make('content1')
                            ->content(new HtmlString('<p>Voor het gebruik van provinciale wegen, of in het geval van een wegwedstrijd die door meerdere gemeenten binnen de provincie voert dient er <a href="https://www.limburg.nl/@1161/wedstrijden-weg" target="_blank" rel="noopener noreferrer">een verzoek voor ontheffing van de openbare weg </a>gericht te worden aan de Provincie Limburg.</p>')),
                        Placeholder::make('content39')
                            ->content(new HtmlString('<p>Voor het afgeven van een ontheffing voor het kruisen van wegen/waters van het Waterschap dient u een aanvraag te doen via <a href="https://www.waterschaplimburg.nl/overons/regels-wetgeving-0/melding-vergunning/" target="_blank" rel="noopener noreferrer">de website</a>.</p>')),
                        Placeholder::make('content41')
                            ->content(new HtmlString('<p>Voor het afgeven van een ontheffing voor het kruisen van wegen/waters van het Rijkswaterstaat dient u een aanvraag te doen via <a href="https://www.rijkswaterstaat.nl/wegen/wetten-regels-en-vergunningen/vergunningen-rijkswegen" target="_blank" rel="noopener noreferrer">de website van Rijkswaterstaat</a>.</p>')),
                        Placeholder::make('content40')
                            ->content(new HtmlString('<p>Voor het afgeven van een ontheffing voor het kruisen van wegen/paden van het Staatsbosheer dient u een aanvraag te doen via <a href="https://www.staatsbosbeheer.nl/contact/evenementen-aanmelden" target="_blank" rel="noopener noreferrer">de website van Staatsbosbeheer</a>.</p>')),
                        Placeholder::make('routeStartEndContent2')
                            ->content(new HtmlString('<p>{% if not inGemeentenResponse.line.start or not inGemeentenResponse.line.end %}</p><p>Er is nog geen route ingetekend of de route start of eindigt &nbsp;buiten de gemeenten die gebruik maken van Eventloket.&nbsp;</p><p>{% elif inGemeentenResponse.line.start_end_equal == False %}</p><p>De route start in de gemeente <strong>{{ inGemeentenResponse.line.start.name }}</strong> en eindigt in de gemeente <strong>{{ inGemeentenResponse.line.end.name }}</strong>, hierdoor kan het zijn dat u bij beide gemeenten een vergunningaanvraag moet doen. U dient vult dit formulier helemaal in voor 1 gemeente, als u de aanvraag vervolgens heeft gedaan kunt u binnen de aanvraag in Eventloket de knop “Nieuwe aanvraag” gebruiken om een nieuw aanvraag te starten waarbij (een deel van) het formulier al vooraf ingevuld is.</p><p>{% elif inGemeentenResponse.line.start_end_equal == True %}</p><p>De route start en eindigt binnen de gemeente <strong>{{ inGemeentenResponse.line.start.name }}.</strong></p><p>{% endif %}</p>')),
                    ]),
                Placeholder::make('NotWithin')
                    ->content(new HtmlString('<h3><span style="color:#e64c4c;">Let op</span></h3><p>Een ingevoerd adres of (een deel van) een getekende route of locatie valt buiten de gemeenten die EventLoket gebruiken.</p><p>Eventloket wordt gebruikt door de gemeenten:&nbsp;{{ alleGemeenteNamen }}</p>')),
                Radio::make('userSelectGemeente')
                    ->label('De ingevoerde locatie(s) of route valt binnen of doorkruist meerdere gemeenten, wat is de gemeente waarbinnen u de aanvraag wilt doen?')
                    ->required(),
                Placeholder::make('contentRouteDoorkuistMeerdereGemeenteInfo')
                    ->content(new HtmlString('<p>De ingetekende route doorkruist de volgende gemeente(n): {{ routeDoorGemeentenNamen|join:", " }} &nbsp;U gaat de vergunningaanvraag invullen voor de gemeente&nbsp;<strong> {% get_value evenementInGemeente \'name\' %}</strong>, de overige gemeente(n) die gebruik maken van Eventloket op de route zullen automatisch geïnformeerd worden.</p>')),
                Placeholder::make('content200')
                    ->content(new HtmlString('<p>U gaat verder met deze aanraag voor de gemeente:<strong> {% get_value evenementInGemeente \'name\' %}</strong></p>')),
            ]);
    }
}
