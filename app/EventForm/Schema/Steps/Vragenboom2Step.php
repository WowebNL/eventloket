<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;

/**
 * @openforms-step-uuid ae44ab5b-c068-4ceb-b121-6e6907f78ef9
 *
 * @openforms-step-index 8
 */
final class Vragenboom2Step
{
    public static function make(): Step
    {
        return Step::make('Vergunningsaanvraag: soort')
            ->schema([
                Radio::make('voordatUVerderGaatMetHetBeantwoordenVanDeVragenVoorUwEvenementWillenWeGraagWetenOfUEerderEenVooraankondigingHeeftIngevuldVoorDitEvenement')
                    ->label('Voordat u verder gaat met het beantwoorden van de vragen voor uw evenement willen we graag weten of u eerder een vooraankondiging heeft ingevuld voor dit evenement?')
                    ->required(),
                TextInput::make('watIsTijdensDeHeleDuurVanUwEvenementWatIsDeNaamVanHetEvenementVergunningHetTotaalAantalAanwezigePersonenVanAlleDagenBijElkaarOpgeteld')
                    ->label('Wat is tijdens de hele duur van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} het totaal aantal aanwezige personen van alle dagen bij elkaar opgeteld?')
                    ->numeric()
                    ->required(),
                TextInput::make('watIsHetMaximaalAanwezigeAantalPersonenDatOpEnigMomentAanwezigKanZijnBijUwEvenementX')
                    ->label('Wat is het maximaal aanwezige aantal personen dat op enig moment aanwezig kan zijn bij uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?')
                    ->numeric()
                    ->required(),
                Radio::make('watZijnDeBelangrijksteLeeftijdscategorieenVanHetPubliekTijdensUwEvenement')
                    ->label('Wat zijn de belangrijkste leeftijdscategorieen van het publiek tijdens uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?')
                    ->options([
                        '018Jaar' => '0 - 18 jaar',
                        '1830Jaar' => '18 - 30 jaar',
                        '3045Jaar' => '30 - 45 jaar',
                        '45JaarEnOuder' => '45 jaar en ouder',
                    ])
                    ->required(),
                Radio::make('isUwEvenementXGratisToegankelijkVoorHetPubliek')
                    ->label('Is uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} gratis toegankelijk voor het publiek?')
                    ->required(),
                CheckboxList::make('kruisAanWatVanToepassingIsVoorUwEvenementX')
                    ->label('Kruis aan wat van toepassing is voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?')
                    ->options([
                        'A1' => '(Versterkte) muziek',
                        'A2' => 'Versterkte spraak',
                        'A3' => 'Bouwsels plaatsen groter dan 10m2, zoals tenten of podia',
                        'A4' => ' Een kansspel organiseren, zoals een bingo of loterij ',
                        'A5' => 'Alcoholhoudende dranken verkopen',
                        'A6' => 'Niet-alcoholische dranken verkopen ',
                        'A7' => 'Eten bereiden of verkopen',
                        'A8' => 'Het evenement belemmert het doorgaand verkeer (omleiden, vertragen)',
                        'A9' => 'Een deel van een doorgaande weg gebruiken voor het evenement',
                        'A10' => 'Een (een deel van) de weg of vaarweg afsluiten voor doorgaand verkeer',
                        'A11' => 'Toegang voor hulpdiensten  tot de evenementlocatie(s) (en de omliggende percelen en gebouwen) is beperkt.',
                    ]),
                CheckboxList::make('welkeVoorzieningenZijnAanwezigBijUwEvenement')
                    ->label('Welke voorzieningen zijn aanwezig bij uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?')
                    ->options([
                        'A12' => 'WC\'s plaatsen (of bestaande gebruiken) ',
                        'A13' => 'Douches plaatsen (of bestaande gebruiken) ',
                        'A53' => 'Beveiligers inhuren',
                        'A14' => 'Medische voorzieningen  treffen (Veldnorm Evenementenzorg - EHBO)',
                        'A15' => 'Verzorging van kinderen jonger dan 12 jaar',
                        'A16' => 'Verzorging mensen met een lichamelijke of geestelijke beperking',
                        'A17' => 'Overnachtingen',
                        'A18' => 'Tenten of Podia',
                        'A19' => 'Tribunes',
                        'A20' => 'Overkappingen',
                        'A21' => 'Omheining van de evenementenlocatie(s)',
                        'A22' => 'Overige bouwwerken',
                    ])
                    ->live(),
                Textarea::make('welkeOverigeBouwwerkenGaatUPlaatsen')
                    ->label('Welke overige bouwwerken gaat u plaatsen?')
                    ->required()
                    ->maxLength(10000)
                    ->visible(fn (Get $get): bool => $get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A22') === true),
                CheckboxList::make('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX')
                    ->label('Welke voorwerpen gaat u plaatsen bij uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?')
                    ->options([
                        'A23' => 'Verkooppunten  voor toegangskaarten',
                        'A24' => 'Verkooppunten  voor consumptiemunten of -bonnen',
                        'A25' => 'Speeltoestellen Attractietoestellen',
                        'A26' => 'Aggregaten,  brandstofopslag en andere brandgevaarlijke stoffen',
                        'A27' => 'Geluidstorens',
                        'A28' => 'Lichtmasten',
                        'A29' => 'Marktkramen',
                        'A30' => 'Andere voorwerpen',
                    ])
                    ->live(),
                Textarea::make('welkeAnderVoorwerpenGaatUPlaatsenBijEvenementX')
                    ->label('welke ander voorwerpen gaat u plaatsen bij evenement {{ watIsDeNaamVanHetEvenementVergunning }}?')
                    ->maxLength(10000)
                    ->visible(fn (Get $get): bool => $get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A30') === true),
                CheckboxList::make('kruisAanWelkeOverigeMaatregelenGevolgenVanToepassingZijnVoorUwEvenementX')
                    ->label('Kruis aan welke overige maatregelen/gevolgen van toepassing zijn voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?')
                    ->options([
                        'A31' => 'Toegangscontrole',
                        'A32' => '(Laten) aanpassen locatie en/of verwijderen straatmeubilair',
                        'A33' => 'Er ontstaat extra afval',
                        'A34' => 'Gebruik van eco-glazen of statiegeld op (plastic)glazen',
                        'A35' => 'Er zijn vrij toegankelijke drinkwatervoorzieningen beschikbaar',
                        'A36' => 'Waterverneveling, bijvoorbeeld door fonteinen, douches of andere waterbronnen (Legionellapreventie)',
                    ]),
                CheckboxList::make('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX')
                    ->label('Welke van de onderstaande activiteiten vinden verder nog plaats tijdens uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?')
                    ->options([
                        'A37' => 'Ballonnen oplaten',
                        'A38' => 'Lasershow',
                        'A39' => '(Reclame)zeppelin oplaten',
                        'A40' => 'Activiteiten met dieren',
                        'A41' => 'Vuurwerk afsteken',
                        'A42' => 'Tatoeages,  piercings, of permanente make-up aanbrengen',
                        'A43' => 'Open vuur (vuurkorven, feestvuren etc.)',
                        'A44' => 'Kanon-, carbid- of kamerschieten',
                        'A45' => 'Showeffecten',
                        'A106' => 'Gebruik van drones',
                        'A46' => 'Overig',
                    ])
                    ->live(),
                Textarea::make('welkActiviteitBetreftUwEvenementX')
                    ->label('Welk activiteit betreft uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?')
                    ->required()
                    ->maxLength(10000)
                    ->visible(fn (Get $get): bool => $get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A46') === true),
                CheckboxList::make('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX')
                    ->label('Kruis aan wat voor overige kenmerken van toepassing zijn voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}')
                    ->options([
                        'A48' => 'Voertuigen parkeren die langer zijn dan 6 meter en/of hoger dan 2,40 meter',
                        'A49' => 'Voorwerpen op de weg plaatsen',
                        'A50' => 'Bewegwijzering aanbrengen',
                        'A51' => 'Verkeersregelaars inzetten',
                        'A52' => 'Vervoersmaatregelen nemen (parkeren, openbaar vervoer, pendelbussen)',
                    ]),
                Radio::make('isUwEvenementToegankelijkVoorMensenMetEenBeperking')
                    ->label('Is uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} toegankelijk voor mensen met een beperking?')
                    ->required()
                    ->visible(fn (Get $get): bool => $get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A16') === true)
                    ->live(),
                TextInput::make('voorHoeveelMensenMetEenLichamelijkeOfGeestelijkeBeperkingVerzorgtUOpvangTijdensUwEvenementX')
                    ->label('Voor hoeveel mensen met een lichamelijke of geestelijke beperking verzorgt u opvang tijdens uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?')
                    ->numeric()
                    ->required()
                    ->visible(fn (Get $get): bool => $get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A16') === true),
                Textarea::make('welkeMaatregelenHeeftUGenomenOmMensenMetEenBeperkingOngehinderdDeelTeLatenNemenAanUwEvenement')
                    ->label('Welke maatregelen heeft u genomen om mensen met een beperking ongehinderd deel te laten nemen aan uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?')
                    ->required()
                    ->maxLength(10000)
                    ->visible(fn (Get $get): bool => $get('isUwEvenementToegankelijkVoorMensenMetEenBeperking') === 'Ja'),
            ]);
    }
}
