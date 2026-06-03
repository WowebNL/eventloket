<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use App\EventForm\Components\InfoText;
use App\EventForm\Components\JaNeeOptions;
use App\EventForm\Schema\Hidden;
use App\EventForm\Schema\Label;
use Dotswan\MapPicker\Fields\Map;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;

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
                        InfoText::info('content23', '<p>U heeft aangegeven om toiletten te plaatsen (of bestaande te gebruiken) . Hierinder volgen een a<strong>antal vragen hierover.</strong></p>'),
                        TextInput::make('hoeveelVasteToilettenZijnBeschikbaar')
                            ->label('Hoeveel vaste toiletten zijn beschikbaar?')
                            ->numeric()
                            ->required(),
                        TextInput::make('hoeveelTijdelijkeChemischeToilettenZijnErBeschikbaar')
                            ->label('Hoeveel tijdelijke chemische toiletten / Dixies zijn er beschikbaar?')
                            ->numeric()
                            ->required()
                            ->belowContent([
                                Icon::make(Heroicon::InformationCircle),
                                'Een chemisch toilet heeft geen wateraansluiting nodig en is niet aangesloten op het riool.',
                            ])
                            ->live(),
                        TextInput::make('hoeveelTijdelijkeDixiToilettenZijnErBeschikbaar')
                            ->label('Hoeveel tijdelijke gespoelde toiletten zijn er beschikbaar?')
                            ->numeric()
                            ->required()
                            ->belowContent([
                                Icon::make(Heroicon::InformationCircle),
                                'Een gespoeld toilet heeft een wateraansluiting nodig en is aangesloten op het riool.',
                            ]),
                        TextInput::make('hoeveelPlaskruizenZijnErBeschikbaar')
                            ->label('Hoeveel plaskruizen zijn er beschikbaar?')
                            ->numeric()
                            ->required()
                            ->belowContent([
                                Icon::make(Heroicon::InformationCircle),
                                'Een plaskruis heeft geen wateraansluiting nodig en is niet aangesloten op het riool.',
                            ]),
                        TextInput::make('welkPercentageVanDeToilettenIsVoorHeren')
                            ->label('Hoeveel toiletten zijn voor heren?')
                            ->numeric()
                            ->required()
                            ->belowContent([
                                Icon::make(Heroicon::InformationCircle),
                                'Reken op 1 herentoilet per 300 mannen + 1 urinoir per 75 mannen. Ingeval van langere duur (>6 uur) verhoog het aantal met 20%-30%. Ingeval van alcoholgebruik verhoog met 25%-35%.',
                            ]),
                        TextInput::make('aantalToilettenDamen')
                            ->label('Hoeveel toiletten zijn voor dames?')
                            ->numeric()
                            ->required()
                            ->belowContent([
                                Icon::make(Heroicon::InformationCircle),
                                'Reken op 1 damestoilet per 100 vrouwen. Ingeval van langere duur (>6 uur) verhoog het aantal met 20%-30%. Ingeval van alcoholgebruik verhoog met 25%-35%.',
                            ]),
                        TextInput::make('aantalToilettenMiva')
                            ->label('Hoeveel toiletten zijn voor MIVA/rolstoelgebruikers?')
                            ->numeric()
                            ->required()
                            ->belowContent([
                                Icon::make(Heroicon::InformationCircle),
                                'Reken op minimaal 1 toilet per 2000 bezoekers.',
                            ]),
                        TextInput::make('handenwaspunten')
                            ->label('Hoeveel handenwaspunten worden er bij de toiletten ingericht op locatie Evenement ')
                            ->numeric()
                            ->required()
                            ->belowContent([
                                Icon::make(Heroicon::InformationCircle),
                                'Let op, dat het volgens de hygiene richtlijnen verplicht is om waspunten aan te bieden. Reken op 1 waspunt per 4 toiletten of 1 waspunt per 200 personen.',
                            ]),
                        Radio::make('reinigtUDeTijdelijkeToilettenOpLocatieEvenementWatIsDeNaamVanHetEvenementVergunning')
                            ->label(Label::render('Reinigt u de tijdelijke toiletten op locatie Evenement {{ watIsDeNaamVanHetEvenementVergunning }}?'))
                            ->options(JaNeeOptions::OPTIONS)
                            ->required(),
                        Radio::make('gebruikenDeTijdelijkeToilettenOpLocatieEvenementWatIsDeNaamVanHetEvenementVergunningVoorHetSpoelenOppervlaktewater')
                            ->label(Label::render('Gebruiken de tijdelijke toiletten op locatie Evenement {{ watIsDeNaamVanHetEvenementVergunning }} voor het spoelen oppervlaktewater?'))
                            ->options(JaNeeOptions::OPTIONS)
                            ->required()
                            ->belowContent([
                                Icon::make(Heroicon::InformationCircle),
                                'Met oppervlaktewater wordt bedoeld water, dat zich boven de grond bevindt, zoals bijv. in sloten, rivieren of meren.',
                            ]),
                    ])
                    ->hidden(Hidden::rule('wCs')),
                Fieldset::make('Douche\'s')
                    ->schema([
                        InfoText::info('content24', '<p>U heeft aangegeven, dat er douches geplaatst worden (of bestaande gebruiken). Hieronder volgt een aantal vragen hierover.</p>'),
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
                            ->options(JaNeeOptions::OPTIONS)
                            ->required(),
                    ])
                    ->hidden(Hidden::rule('douches')),
                Fieldset::make('EHBO')
                    ->schema([
                        InfoText::info('content25', '<p>U heeft aangegeven extra medische voorzieningen te treffen (EHBO). Hieronder volgt een aantal vragen daarover.</p><p>Meer informatie vind u op de website van <a href="https://www.evenementenz.org/wp/veldnorm/ " target="_blank" rel="noopener noreferrer">Veldnorm Evenementenzorg</a>.</p>'),
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
                    ->hidden(Hidden::rule('ehbo')),
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
                    ->hidden(Hidden::rule('verzorgingVanKinderenJongerDan12Jaar')),
                Fieldset::make('Overnachtingen')
                    ->schema([
                        TextInput::make('voorHoeveelMensenVerzorgtUOvernachtingenTijdensUwEvenement1')
                            ->label(Label::render('Voor hoeveel mensen verzorgt u overnachtingen tijdens uw Evenement {{ watIsDeNaamVanHetEvenementVergunning }}?'))
                            ->numeric()
                            ->required(),
                        Radio::make('isErSprakeVanOvernachtenDoorPubliekDeelnemers')
                            ->label('Is er sprake van overnachten door publiek/deelnemers?')
                            ->options(JaNeeOptions::OPTIONS)
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
                            ->hidden(Hidden::rule('opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPubliekDeelnemers1')),
                        Radio::make('isErSprakeVanOvernachtenDoorPubliekDeelnemers1')
                            ->label('Is er sprake van overnachten door personeel/organisatie?')
                            ->options(JaNeeOptions::OPTIONS)
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
                            ->hidden(Hidden::rule('opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPersoneelOrganisatie2')),
                    ])
                    ->hidden(Hidden::rule('overnachtingen')),
                Fieldset::make('Bouwsels')
                    ->schema([
                        InfoText::info('content26', '<p>U heeft aangegeven, dat er diverse bouwsels geplaatst worden. Wilt u hier meer infomatie verstrekken over deze bouwsels?</p>'),
                        TextInput::make('watIsHetMaximaleAantalPersonenDatTijdensUwEvenementXAanwezigIsInEenTentOfAndereBeslotenRuimtePodiumBouwwerkEtc')
                            ->label(Label::render('Wat is het maximale aantal personen dat tijdens uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} aanwezig is in een tent of andere besloten ruimte (podium, bouwwerk etc)?'))
                            ->numeric()
                            ->required()
                            ->hidden(Hidden::rule('watIsHetMaximaleAantalPersonenDatTijdensUwEvenementXAanwezigIsInEenTentOfAndereBeslotenRuimtePodiumBouwwerkEtc')),
                    ])
                    ->hidden(Hidden::rule('bouwsels')),
                Fieldset::make('Beveiligers')
                    ->schema([
                        InfoText::info('content36', '<p>U heeft aangegeven, dat u beveiligers wilt inhuren. Hieronder volgt een aantal vragen daarover.</p>'),
                        Textarea::make('gegevensBeveiligingsorganisatieOpLocatieEvenementX1')
                            ->label(Label::render('Gegevens beveiligingsorganisatie op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}'))
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
                        TextInput::make('telefoonnummerBeveiligingsorganisatie1')
                            ->label('Telefoonnummer beveiligingsorganisatie')
                            ->tel()
                            ->required(),
                        TextInput::make('aantalBeveiligersOpLocatieEvenementX1')
                            ->label(Label::render('Aantal beveiligers op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}'))
                            ->numeric()
                            ->required(),
                    ])
                    ->hidden(Hidden::rule('beveiligers1')),
            ]);
    }
}
