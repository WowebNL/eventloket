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
                            ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>U heeft aangegeven om toiletten te plaatsen (of bestaande te gebruiken) . Hierinder volgen een a<strong>antal vragen hierover.</strong></p>', $livewire->state()))),
                        TextInput::make('hoeveelVasteToilettenZijnBeschikbaar')
                            ->label('Hoeveel vaste toiletten zijn beschikbaar?')
                            ->numeric()
                            ->required(),
                        TextInput::make('hoeveelTijdelijkeChemischeToilettenZijnErBeschikbaar')
                            ->label('Hoeveel tijdelijke chemische toiletten / Dixies zijn er beschikbaar?')
                            ->numeric()
                            ->required()
                            ->live(),
                        TextInput::make('hoeveelTijdelijkeDixiToilettenZijnErBeschikbaar')
                            ->label('Hoeveel tijdelijke gespoelde toiletten zijn er beschikbaar?')
                            ->numeric()
                            ->required()
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('hoeveelTijdelijkeDixiToilettenZijnErBeschikbaar');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return $get('hoeveelTijdelijkeChemischeToilettenZijnErBeschikbaar') === '0';
                            }),
                        TextInput::make('welkPercentageVanDeToilettenIsVoorHeren')
                            ->label('Hoeveel toiletten zijn voor heren?')
                            ->numeric()
                            ->required(),
                        TextInput::make('aantalToilettenDamen')
                            ->label('Hoeveel toiletten zijn voor dames?')
                            ->numeric()
                            ->required(),
                        TextInput::make('aantalToilettenMiva')
                            ->label('Hoeveel toiletten zijn voor MIVA/rolstoelgebruikers?')
                            ->numeric()
                            ->required(),
                        TextInput::make('handenwaspunten')
                            ->label('Hoeveel handenwaspunten worden er bij de toiletten ingericht op locatie Evenement ')
                            ->numeric()
                            ->required(),
                        Radio::make('reinigtUDeTijdelijkeToilettenOpLocatieEvenementWatIsDeNaamVanHetEvenementVergunning')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Reinigt u de tijdelijke toiletten op locatie Evenement {{ watIsDeNaamVanHetEvenementVergunning }}?', $livewire->state()))
                            ->options([
                                'Ja' => 'Ja',
                                'Nee' => 'Nee',
                            ])
                            ->required(),
                        Radio::make('gebruikenDeTijdelijkeToilettenOpLocatieEvenementWatIsDeNaamVanHetEvenementVergunningVoorHetSpoelenOppervlaktewater')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Gebruiken de tijdelijke toiletten op locatie Evenement {{ watIsDeNaamVanHetEvenementVergunning }} voor het spoelen oppervlaktewater?', $livewire->state()))
                            ->options([
                                'Ja' => 'Ja',
                                'Nee' => 'Nee',
                            ])
                            ->required(),
                    ])
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('wCs') !== false),
                Fieldset::make('Douche\'s')
                    ->schema([
                        TextEntry::make('content24')
                            ->hiddenLabel()
                            ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>U heeft aangegeven, dat er douches geplaatst worden (of bestaande gebruiken). Hieronder volgen een aantal vragen hierover.</p>', $livewire->state()))),
                        TextInput::make('hoeveelVasteDouchevoorzieningenZijnBeschikbaar')
                            ->label('Hoeveel vaste douchevoorzieningen zijn beschikbaar?')
                            ->numeric()
                            ->required(),
                        TextInput::make('hoeveelTijdelijkeDouchevoorzieningenZijnBeschikbaar')
                            ->label('Hoeveel tijdelijke douchevoorzieningen zijn beschikbaar?')
                            ->numeric()
                            ->required(),
                        Radio::make('wordenDeDouchesTussentijdsSchoonGemaakt')
                            ->label('Worden de douches tussentijds schoon gemaakt?')
                            ->options([
                                'Ja' => 'Ja',
                                'Nee' => 'Nee',
                            ])
                            ->required(),
                    ])
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('douches') !== false),
                Fieldset::make('EHBO')
                    ->schema([
                        TextEntry::make('content25')
                            ->hiddenLabel()
                            ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>U heeft aangegeven extra medische voorzieningen te treffen (EHBO). Hieronder volgen een aantal vragen daarover.</p><p>Meer informatie vind u op de website van <a href="https://www.evenementenz.org/wp/veldnorm/ " target="_blank" rel="noopener noreferrer">Veldnorm Evenementenzorg</a>.</p>', $livewire->state()))),
                        TextInput::make('aantalVasteEersteHulpposten')
                            ->label('Aantal vaste eerste hulpposten')
                            ->numeric()
                            ->required(),
                        TextInput::make('aantalMobieleEersteHulpteams')
                            ->label('Aantal mobiele eerste hulpteams')
                            ->numeric()
                            ->required(),
                        TextInput::make('aantalEersteHulpverlenersMetNiveauBasisEersteHulp')
                            ->label('Aantal Eerste hulpverleners met niveau \'Basis eerste hulp\'')
                            ->numeric()
                            ->required(),
                        TextInput::make('aantalEersteHulpverlenersMetNiveauEvenementenEersteHulp')
                            ->label('Aantal Eerste hulpverleners met niveau \'Evenementen eerste hulp\'')
                            ->numeric()
                            ->required(),
                        TextInput::make('aantalZorgprofessionalsMetNiveauBasisZorg')
                            ->label('Aantal Zorgprofessionals met niveau \'Basis Zorg\'')
                            ->numeric()
                            ->required(),
                        TextInput::make('aantalZorgprofessionalsMetNiveauSpoedZorg')
                            ->label('Aantal Zorgprofessionals met niveau \'Spoed Zorg\'')
                            ->numeric()
                            ->required(),
                        TextInput::make('aantalZorgprofessionalsMetNiveauMedischeZorg')
                            ->label('Aantal Zorgprofessionals met niveau \'Medische Zorg\'')
                            ->numeric()
                            ->required(),
                        TextInput::make('aantalZorgprofessionalsMetNiveauSpecialistischeSpoedzorg')
                            ->label('Aantal Zorgprofessionals met niveau \'Specialistische Spoedzorg\'')
                            ->numeric()
                            ->required(),
                        TextInput::make('aantalZorgprofessionalsMetNiveauArtsenSpecialistischeSpoedzorg')
                            ->label('Aantal Zorgprofessionals met niveau \'Artsen specialistische Spoedzorg\'')
                            ->numeric()
                            ->required(),
                        TextInput::make('welkeOrganisatieVerzorgtDeEersteHulp')
                            ->label('Welke organisatie verzorgt de eerste hulp?')
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('ehbo') !== false),
                Fieldset::make('Verzorging van kinderen jonger dan 12 jaar')
                    ->schema([
                        TextInput::make('voorHoeveelKinderenInTotaalJongerDan12JaarIsVerzorgingNodig')
                            ->label('Voor hoeveel kinderen in totaal jonger dan 12 jaar is verzorging nodig?')
                            ->numeric()
                            ->required(),
                        TextInput::make('hoeveelVanHetTotaalAantalKinderenOnder12JaarValtInDeLeeftijdscategorieVan04Jaar')
                            ->label('Hoeveel van het totaal aantal kinderen onder 12 jaar valt in de leeftijdscategorie van 0-4 jaar?')
                            ->numeric()
                            ->required(),
                        TextInput::make('hoeveelVanHetTotaalAantalKinderenOnder12JaarValtInDeLeeftijdscategorieVan512Jaar')
                            ->label('Hoeveel van het totaal aantal kinderen onder 12 jaar valt in de leeftijdscategorie van 5-12 jaar?')
                            ->numeric()
                            ->required(),
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
                    ])
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('verzorgingVanKinderenJongerDan12Jaar') !== false),
                Fieldset::make('Overnachtingen')
                    ->schema([
                        TextInput::make('voorHoeveelMensenVerzorgtUOvernachtingenTijdensUwEvenement1')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Voor hoeveel mensen verzorgt u overnachtingen tijdens uw Evenement {{ watIsDeNaamVanHetEvenementVergunning }}?', $livewire->state()))
                            ->numeric()
                            ->required(),
                        Radio::make('isErSprakeVanOvernachtenDoorPubliekDeelnemers')
                            ->label('Is er sprake van overnachten door publiek/deelnemers?')
                            ->options([
                                'Ja' => 'Ja',
                                'Nee' => 'Nee',
                            ])
                            ->required()
                            ->live(),
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
                            ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPubliekDeelnemers1') !== false),
                        Radio::make('isErSprakeVanOvernachtenDoorPubliekDeelnemers1')
                            ->label('Is er sprake van overnachten door personeel/organisatie?')
                            ->options([
                                'Ja' => 'Ja',
                                'Nee' => 'Nee',
                            ])
                            ->required()
                            ->live(),
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
                            ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPersoneelOrganisatie2') !== false),
                    ])
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('overnachtingen') !== false),
                Fieldset::make('Bouwsels')
                    ->schema([
                        TextEntry::make('content26')
                            ->hiddenLabel()
                            ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>U heeft aangegeven, dat er diverse bouwsels geplaatst worden. Wilt u hier meer infomatie verstrekken over deze bouwsels?</p>', $livewire->state()))),
                        TextInput::make('watIsHetMaximaleAantalPersonenDatTijdensUwEvenementXAanwezigIsInEenTentOfAndereBeslotenRuimtePodiumBouwwerkEtc')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Wat is het maximale aantal personen dat tijdens uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} aanwezig is in een tent of andere besloten ruimte (podium, bouwwerk etc)?', $livewire->state()))
                            ->numeric()
                            ->required()
                            ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('watIsHetMaximaleAantalPersonenDatTijdensUwEvenementXAanwezigIsInEenTentOfAndereBeslotenRuimtePodiumBouwwerkEtc') !== false),
                    ])
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('bouwsels') !== false),
                Fieldset::make('Beveiligers')
                    ->schema([
                        TextEntry::make('content36')
                            ->hiddenLabel()
                            ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>U heeft aangegeven, dat u beveiligers wilt inhuren. Hieronder volgen een aantal vragen daarover.</p>', $livewire->state()))),
                        Textarea::make('gegevensBeveiligingsorganisatieOpLocatieEvenementX1')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Gegevens beveiligingsorganisatie op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}', $livewire->state()))
                            ->required()
                            ->maxLength(10000),
                        TextInput::make('vergunningnummerBeveiligingsorganisatie1')
                            ->label('Vergunningnummer beveiligingsorganisatie')
                            ->numeric()
                            ->required(),
                        TextInput::make('vestigingsplaatsBeveiligingsorganisatie1')
                            ->label('Vestigingsplaats beveiligingsorganisatie')
                            ->required()
                            ->maxLength(1000),
                        TextInput::make('aantalBeveiligersOpLocatieEvenementX1')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Aantal beveiligers op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}', $livewire->state()))
                            ->numeric()
                            ->required(),
                    ])
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('beveiligers1') !== false),
            ]);
    }
}
