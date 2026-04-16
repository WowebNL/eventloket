<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use Dotswan\MapPicker\Fields\Map;
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
 * @openforms-step-uuid f4e91db5-fd74-4eba-b818-96ed2cc07d84
 *
 * @openforms-step-index 10
 */
final class VergunningsaanvraagVoorzieningenStep
{
    public static function make(): Step
    {
        return Step::make('Vergunningsaanvraag: voorzieningen')
            ->schema([
                Fieldset::make('WC\'s')
                    ->schema([
                        Placeholder::make('content23')
                            ->content(new HtmlString('<p>U heeft aangegeven om toiletten te plaatsen (of bestaande te gebruiken) . Hierinder volgen een a<strong>antal vragen hierover.</strong></p>')),
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
                            ->hidden(fn (Get $get): bool => $get('hoeveelTijdelijkeChemischeToilettenZijnErBeschikbaar') === '0'),
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
                            ->label('Reinigt u de tijdelijke toiletten op locatie Evenement {{ watIsDeNaamVanHetEvenementVergunning }}?')
                            ->required(),
                        Radio::make('gebruikenDeTijdelijkeToilettenOpLocatieEvenementWatIsDeNaamVanHetEvenementVergunningVoorHetSpoelenOppervlaktewater')
                            ->label('Gebruiken de tijdelijke toiletten op locatie Evenement {{ watIsDeNaamVanHetEvenementVergunning }} voor het spoelen oppervlaktewater?')
                            ->required(),
                    ])
                    ->hidden(),
                Fieldset::make('Douche\'s')
                    ->schema([
                        Placeholder::make('content24')
                            ->content(new HtmlString('<p>U heeft aangegeven, dat er douches geplaatst worden (of bestaande gebruiken). Hieronder volgen een aantal vragen hierover.</p>')),
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
                            ->required(),
                    ])
                    ->hidden(),
                Fieldset::make('EHBO')
                    ->schema([
                        Placeholder::make('content25')
                            ->content(new HtmlString('<p>U heeft aangegeven extra medische voorzieningen te treffen (EHBO). Hieronder volgen een aantal vragen daarover.</p><p>Meer informatie vind u op de website van <a href="https://www.evenementenz.org/wp/veldnorm/ " target="_blank" rel="noopener noreferrer">Veldnorm Evenementenzorg</a>.</p>')),
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
                    ->hidden(),
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
                                    ->required(),
                            ]),
                    ])
                    ->hidden(),
                Fieldset::make('Overnachtingen')
                    ->schema([
                        TextInput::make('voorHoeveelMensenVerzorgtUOvernachtingenTijdensUwEvenement1')
                            ->label('Voor hoeveel mensen verzorgt u overnachtingen tijdens uw Evenement {{ watIsDeNaamVanHetEvenementVergunning }}?')
                            ->numeric()
                            ->required(),
                        Radio::make('isErSprakeVanOvernachtenDoorPubliekDeelnemers')
                            ->label('Is er sprake van overnachten door publiek/deelnemers?')
                            ->required(),
                        Repeater::make('opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPubliekDeelnemers1')
                            ->label('Op welke locatie of locaties is er sprake van overnachten door publiek/deelnemers?')
                            ->schema([
                                Map::make('locatieVanOvernachtenDoorPubliekDeelnemers')
                                    ->label('Locatie van overnachten door publiek/deelnemers')
                                    ->required(),
                            ])
                            ->hidden(),
                        Radio::make('isErSprakeVanOvernachtenDoorPubliekDeelnemers1')
                            ->label('Is er sprake van overnachten door personeel/organisatie?')
                            ->required(),
                        Repeater::make('opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPersoneelOrganisatie2')
                            ->label('Op welke locatie of locaties is er sprake van overnachten door personeel/organisatie?')
                            ->schema([
                                Map::make('locatieVanOvernachtenDoorPersoneelOrganisatie1')
                                    ->label('Locatie van overnachten door personeel/organisatie')
                                    ->required(),
                            ])
                            ->hidden(),
                    ])
                    ->hidden(),
                Fieldset::make('Bouwsels')
                    ->schema([
                        Placeholder::make('content26')
                            ->content(new HtmlString('<p>U heeft aangegeven, dat er diverse bouwsels geplaatst worden. Wilt u hier meer infomatie verstrekken over deze bouwsels?</p>')),
                        TextInput::make('watIsHetMaximaleAantalPersonenDatTijdensUwEvenementXAanwezigIsInEenTentOfAndereBeslotenRuimtePodiumBouwwerkEtc')
                            ->label('Wat is het maximale aantal personen dat tijdens uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} aanwezig is in een tent of andere besloten ruimte (podium, bouwwerk etc)?')
                            ->numeric()
                            ->required()
                            ->hidden(),
                    ])
                    ->hidden(),
                Fieldset::make('Beveiligers')
                    ->schema([
                        Placeholder::make('content36')
                            ->content(new HtmlString('<p>U heeft aangegeven, dat u beveiligers wilt inhuren. Hieronder volgen een aantal vragen daarover.</p>')),
                        Textarea::make('gegevensBeveiligingsorganisatieOpLocatieEvenementX1')
                            ->label('Gegevens beveiligingsorganisatie op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}')
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
                            ->label('Aantal beveiligers op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}')
                            ->numeric()
                            ->required(),
                    ])
                    ->hidden(),
            ]);
    }
}
