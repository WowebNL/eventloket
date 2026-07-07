<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use App\EventForm\Components\InfoText;
use App\EventForm\Schema\Hidden;
use App\EventForm\State\FormState;
use App\EventForm\Support\SafeDateTime;
use Filament\Forms\Components\Radio;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * @openforms-step-uuid c75cc256-6729-4684-9f9b-ede6265b3e72
 *
 * @openforms-step-index 7
 */
final class RisicoscanStep
{
    public const UUID = 'c75cc256-6729-4684-9f9b-ede6265b3e72';

    public static function make(): Step
    {
        return Step::make('Risicoscan')
            ->key(self::UUID)
            ->schema([
                InfoText::info('content', function (FormState $state): string {
                    $intro = '<p>We stellen u nu een aantal standaard-vragen om een inschatting te maken in welke risico-categorie je evenement valt. Dit kan A-laag, B-middelmatig of C-hoog zijn. De risico-categorie is een indicator voor de hulpdiensten Politie, Brandweer en GHOR om hun inzet te bepalen.</p>';

                    $a = $state->get('gemeenteVariabelen.indieningstermijn_a');
                    $b = $state->get('gemeenteVariabelen.indieningstermijn_b');
                    $c = $state->get('gemeenteVariabelen.indieningstermijn_c');
                    $gemeente = $state->get('evenementInGemeente');
                    $gemeenteNaam = is_array($gemeente) ? ($gemeente['name'] ?? null) : null;

                    if ($gemeenteNaam && ($a || $b || $c)) {
                        $intro .= '<p>De gemeente '.e($gemeenteNaam).' hanteert de volgende indieningstermijnen:</p><ul>';
                        if ($a) {
                            $intro .= '<li>A (klein): <strong>'.((int) $a).' weken</strong> voor de startdatum</li>';
                        }
                        if ($b) {
                            $intro .= '<li>B (middelgroot): <strong>'.((int) $b).' weken</strong> voor de startdatum</li>';
                        }
                        if ($c) {
                            $intro .= '<li>C (groot): <strong>'.((int) $c).' weken</strong> voor de startdatum</li>';
                        }
                        $intro .= '</ul>';
                    }

                    return $intro;
                }),
                Radio::make('watIsDeAantrekkingskrachtVanHetEvenement')
                    ->label('Wat is de aantrekkingskracht van het evenement?')
                    ->options([
                        '0.5' => 'Wijk of buurt',
                        '1' => 'Dorp',
                        '1.5' => 'Gemeentelijk',
                        '2' => 'Regionaal',
                        '2.5' => 'Nationaal',
                        '3' => 'Internationaal',
                    ])
                    ->required()
                    ->belowContent([
                        Icon::make(Heroicon::InformationCircle),
                        'Kies hier de optie waarvan je verwacht, dat er het grootste aantal bezoekers/deelnemers vandaan zal komen.',
                    ])
                    ->live(),
                Radio::make('watIsDeBelangrijksteLeeftijdscategorieVanDeDoelgroep')
                    ->label('Wat is de belangrijkste leeftijdscategorie van de doelgroep?')
                    ->options([
                        '0.25' => '0-15 jaar / met begeleiding',
                        '0.5' => '0-15 jaar / zonder begeleiding',
                        '0.75' => '15-18 jaar',
                        '0.5__2' => '18-30 jaar',
                        '0.25__2' => '30-70 jaar',
                        '1' => '70+ jaar',
                        '0.75__2' => 'Alle leeftijden',
                    ])
                    ->required()
                    ->belowContent([
                        Icon::make(Heroicon::InformationCircle),
                        'Kies hier de leeftijdscategorie waarvan je het grootste aantal bezoekers/deelnemers van verwacht.',
                    ])
                    ->live(),
                Radio::make('isErSprakeVanZanwezigheidVanPolitiekeAandachtEnOfMediageniekheid')
                    ->label('Is er sprake van aanwezigheid van politieke aandacht en/of mediageniekheid?')
                    ->options([
                        '0' => 'Nee',
                        '1' => 'Ja',
                    ])
                    ->required()
                    ->belowContent([
                        Icon::make(Heroicon::InformationCircle),
                        'Kies hier "Ja" als je denkt, dat je evenement veel aandacht gaat trekken van Radio/TV of bijv. radicale groeperingen als gevolg van de aard van het evenement.',
                    ])
                    ->live(),
                Radio::make('isEenDeelVanDeDoelgroepVerminderdZelfredzaam')
                    ->label('Is een deel van de doelgroep verminderd zelfredzaam?')
                    ->options([
                        '1' => 'Niet zelfredzaam',
                        '0.5' => 'Beperkt zelfredzaam',
                        '0.25' => 'Voldoende zelfredzaam',
                        '0' => 'Volledig zelfredzaam',
                    ])
                    ->descriptions([
                        '1' => 'Personen die zonder hulp niet in staat zijn een gebouw of locatie zelfstandig te verlaten tijdens een calamiteit',
                        '0.5' => 'Personen die vanwege hun fysieke of mentale beperkingen lastiger een gebouw of locatie zelfstandig kunnen verlaten tijdens een calamiteit',
                        '0.25' => ' Personen die met enige hulp en aanwijzingen zelfstandig een gebouw of locatie verlaten tijdens een calamiteit',
                    ])
                    ->required()
                    ->live(),
                Radio::make('isErSprakeVanAanwezigheidVanRisicovolleActiviteiten')
                    ->label('Is er sprake van aanwezigheid van risicovolle activiteiten?')
                    ->options([
                        '0' => 'Nee',
                        '1' => 'Ja',
                    ])
                    ->belowContent([
                        Icon::make(Heroicon::InformationCircle),
                        'Met risicovolle activiteiten worden activiteiten bedoeld, die een risico op ongevallen/ongelukken hebben. Denk bijv. aan vuurwerk, vuurspuwen, monstertruckshow e.d.',
                    ])
                    ->required()
                    ->live(),
                Radio::make('watIsHetGrootsteDeelVanDeSamenstellingVanDeDoelgroep')
                    ->label('Wat is het grootste deel van de samenstelling van de doelgroep?')
                    ->options([
                        '0.5' => 'Alleen toeschouwers',
                        '0.75' => 'Combinatie toeschouwers en deelnemers',
                        '1' => 'Alleen deelnemers',
                    ])
                    ->required()
                    ->live(),
                Radio::make('isErSprakeVanOvernachten')
                    ->label('Is er sprake van overnachten?')
                    ->options([
                        '0' => 'Er wordt niet overnacht of er wordt overnacht op een daartoe bestemde locatie',
                        '1' => 'Er wordt overnacht op een niet daartoe bestemde locatie',
                    ])
                    ->required()
                    ->belowContent([
                        Icon::make(Heroicon::InformationCircle),
                        'Een "daartoe bestemde locatie" is een locatie die is ingericht om te overnachten. Denk aan een hotel, ingerichte camping of scoutingruimte met de gebruikersvoorwaarden om te overnachten. Oftewel er al eens nagedacht over inrichting en risico\'s voor deze bestemming.',
                    ])
                    ->live(),
                Radio::make('isErGebruikVanAlcoholEnDrugs')
                    ->label('Is er gebruik van alcohol en drugs?')
                    ->options([
                        '0' => 'Niet aanwezig',
                        '0.5' => 'Aanwezig, zonder risicoverwachting',
                        '1' => 'Aanwezig, met risicoverwachting',
                    ])
                    ->required()
                    ->belowContent([
                        Icon::make(Heroicon::InformationCircle),
                        'Het gaat in deze vraag uiteraard over het mogelijk schenken van alcohol met inachtneming van NIX18 en het gebruik van legale drugs. Met risicoverwachting wordt bedoeld of er mogelijk sprake kan gaan zijn van overmatig gebruik en overlast, die daaruit kan volgen (bijv. vechtpartijen of baldadigheid).',
                    ])
                    ->live(),
                Radio::make('watIsHetAantalGelijktijdigAanwezigPersonen')
                    ->label('Wat is het aantal gelijktijdig aanwezig personen?')
                    ->options([
                        '0' => 'Minder dan 150',
                        '0.25' => '150 - 2.000',
                        '0.5' => '2.000 - 5.000',
                        '0.75' => '5.000 - 10.000',
                        '1' => '10.000 - 15.000',
                        '1.25' => '> 15.000',
                    ])
                    ->required()
                    ->belowContent([
                        Icon::make(Heroicon::InformationCircle),
                        'Gelijktijdig aanwezig op het piekmoment.',
                    ])
                    ->live(),
                Radio::make('inWelkSeizoenVindtHetEvenementPlaats')
                    ->label('In welk seizoen vindt het evenement plaats?')
                    ->options([
                        '0.25' => 'Lente of herfst',
                        '0.5' => 'Zomer of winter',
                    ])
                    ->afterStateHydrated(function (?string $state, Set $set, Get $get): void {
                        if ($state !== null) {
                            return;
                        }
                        $start = SafeDateTime::parse($get('EvenementStart'));
                        if (! $start) {
                            return;
                        }
                        // Lente (mrt–mei) en herfst (sep–nov) → 0.25; zomer (jun–aug) en winter (dec–feb) → 0.5
                        $month = $start->month;
                        $set('inWelkSeizoenVindtHetEvenementPlaats', in_array($month, [3, 4, 5, 9, 10, 11], strict: true) ? '0.25' : '0.5');
                    })
                    ->required()
                    ->belowContent([
                        Icon::make(Heroicon::InformationCircle),
                        'Het antwoord op deze vraag wordt automatisch bepaald op basis van de startdatum van het evenement.',
                    ])
                    ->live(),
                Radio::make('inWelkeLocatieVindtHetEvenementPlaats')
                    ->label('In welke locatie vindt het evenement plaats?')
                    ->options([
                        '0.25' => 'In een gebouw, als een daartoe ingerichte evenementenlocatie',
                        '0.75' => 'In een gebouw, als een niet daartoe ingerichte evenementenlocatie',
                        '0.75__2' => 'In een bouwsel',
                        '0.5' => 'In de open lucht, op een daartoe ingericht evenemententerrein',
                        '0.75__3' => 'In de open lucht, op een niet daartoe ingericht evenemententerrein',
                        '1' => 'Op, aan of in het water',
                    ])
                    ->descriptions([
                        '0.25' => 'bijvoorbeeld een theater, schouwburg, scoutinggebouw',
                        '0.75' => 'bijvoorbeeld een loods',
                        '0.75__2' => 'Bijvoorbeeld een tent',
                        '0.5' => 'Bijvoorbeeld een tent',
                        '0.75__3' => 'bijvoorbeeld een weiland',
                        '1' => 'Deze keuze is van toepassing als de toeschouwers en deelnemers zich daar bevinden',
                    ])
                    ->required()
                    ->belowContent([
                        Icon::make(Heroicon::InformationCircle),
                        'Met "daartoe ingericht" wordt bedoeld, dat er ervaringen zijn en nagedacht is over onderwerpen als bereikbaarheid, ontvluchting, toegangswegen leveranciers-bezoekers, stroomcapaciteit, conflicterende verkeersstromen.',
                    ])
                    ->live(),
                Radio::make('opWelkSoortOndergrondVindtHetEvenementPlaats')
                    ->label('Op welk soort ondergrond vindt het evenement plaats?')
                    ->options([
                        '0.25' => 'Verharde ondergrond',
                        '0.5' => 'Onverharde ondergrond, vochtdoorlatend',
                        '0.75' => 'Onverharde ondergrond, drassig',
                    ])
                    ->descriptions([
                        '0.25' => 'bijvoorbeeld een marktterrein of parkeerplaats',
                        '0.5' => 'bijvoorbeeld een grasveld, waar een tent geplaatst wordt',
                        '0.75' => 'bijvoorbeeld een weiland, waar een motorcross plaatsvindt',
                    ])
                    ->required()
                    ->live(),
                Radio::make('watIsDeTijdsduurVanHetEvenement')
                    ->label('Wat is de tijdsduur van het evenement?')
                    ->options([
                        '0' => 'Minder dan 3 uur tijdens daguren',
                        '0.25' => 'Minder dan 3 uur tijdens avond- en nachturen',
                        '0.5' => 'Tijdsduur van 3-12 uren tijdens de daguren',
                        '0.75' => 'Tijdsduur van 3 - 12 uren tijdens de avond- en nachturen',
                        '1' => 'Hele dag (tijdsduur tussen 12 en 24 uur)',
                        '1.25' => 'Meerdere aaneengesloten dagen',
                    ])
                    ->required()
                    ->belowContent([
                        Icon::make(Heroicon::InformationCircle),
                        'Bij de mogelijkheid tot meerdere categorieën, dan de zwaarste weging hanteren.',
                    ])
                    ->live(),
                Radio::make('welkeBeschikbaarheidVanAanEnAfvoerwegenIsVanToepassing')
                    ->label('Welke beschikbaarheid van aan- en afvoerwegen is van toepassing?')
                    ->options([
                        '1' => 'Geen aan- en afvoerwegen',
                        '0.75' => 'Matige aan- en afvoerwegen',
                        '0.5' => 'Redelijke aan- en afvoerwegen',
                        '0' => 'Goede aan- en afvoerwegen',
                    ])
                    ->required()
                    ->belowContent([
                        Icon::make(Heroicon::InformationCircle),
                        'Het is belangrijk om inzicht te hebben in de calamiteitenroute. Is de calamiteitenroute op dezelfde weg als die van de bezoekers (matig)? Is er een aparte calamiteitenroute middels een brede weg (redelijk). Of zijn er twee calamiteitenroutes afzonderlijk van bezoekers zodat je een eenrichtingsweg kunt instellen wat de doorvoer bevorderd (goed).',
                    ])
                    ->live(),
                InfoText::info('risicoClassificatieContent', function (FormState $state): string {
                    $classificatie = $state->get('risicoClassificatie');
                    $html = '<p>Op basis van uw antwoorden is de voorlopige behandelclassificatie: <strong>'.e($classificatie).'</strong></p>';

                    if ($classificatie) {
                        $key = 'gemeenteVariabelen.indieningstermijn_'.strtolower($classificatie);
                        $weeks = $state->get($key);
                        $gemeente = $state->get('evenementInGemeente');
                        $gemeenteNaam = is_array($gemeente) ? ($gemeente['name'] ?? null) : null;

                        if ($gemeenteNaam && $weeks) {
                            $html .= '<p>De indieningstermijn voor een '.e($classificatie).'-evenement bij de gemeente '.e($gemeenteNaam).' is <strong>'.((int) $weeks).' weken</strong> voor de startdatum van het evenement.</p>';
                        }
                    }

                    return $html;
                })
                    ->hidden(Hidden::rule('risicoClassificatieContent')),
                self::indieningstermijnInfoText(),
            ]);
    }

    private static function indieningstermijnInfoText(): TextEntry
    {
        return TextEntry::make('indieningstermijnContent')
            ->hiddenLabel()
            ->state(function ($livewire): ?HtmlString {
                /** @var FormState $state */
                $state = $livewire->state();
                $status = $state->get('indieningstermijnStatus');

                if ($status === null) {
                    return null;
                }

                $variant = $status['withinDeadline'] ? 'success' : 'warning';
                $text = $status['withinDeadline']
                    ? '<p>Uw aanvraag valt binnen de indieningstermijn van <strong>'.$status['weeks'].' weken</strong> voor de startdatum van het evenement.</p>'
                    : '<p>Let op: de indieningstermijn voor deze risicoclassificatie is <strong>'.$status['weeks'].' weken</strong> voor de startdatum van het evenement. Uw aanvraag valt buiten deze termijn. U kunt de aanvraag nog steeds indienen, maar de kans op afwijzing is groter.</p>';

                return new HtmlString(sprintf(
                    '<div class="eventform-alert eventform-alert-%s">%s</div>',
                    $variant,
                    $text,
                ));
            })
            ->hidden(fn ($livewire): bool => $livewire->state()->get('indieningstermijnStatus') === null);
    }
}
