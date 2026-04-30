<?php

declare(strict_types=1);

namespace Tests\Feature\EventForm\Equivalence\Scenarios;

/**
 * Scenario-provider voor stap 8: Risicoscan.
 *
 * De Risicoscan stelt de organisator 14 vragen. Elke vraag heeft numerieke
 * scores als antwoorden (bv. "Dorp" = 1, "Regionaal" = 2). De som van die
 * 14 scores bepaalt de risico-classificatie volgens de drempel:
 *
 *   som ≤ 6  → A (laag risico)
 *   som ≤ 9  → B (middelhoog risico)
 *   som > 9  → C (hoog risico)
 *
 * De classificatie bepaalt hoeveel aanvullende vragen de organisator in het
 * vervolg van het formulier moet beantwoorden en hoe streng de behandelaar
 * naar de aanvraag kijkt. Een fout in deze berekening heeft dus directe
 * impact op de vergunnings-procedure.
 */
final class RisicoscanScenarios implements ScenarioProvider
{
    public const STAP_RISICOSCAN = 'c75cc256-6729-4684-9f9b-ede6265b3e72';

    public static function categorie(): string
    {
        return 'computation';
    }

    public static function kop(): string
    {
        return 'Risico-classificatie A/B/C op basis van 14 antwoorden';
    }

    public static function inleiding(): string
    {
        return 'De Risicoscan kent elke antwoordoptie een numerieke score toe. De som '
            .'van de 14 scores bepaalt welke classificatie het evenement krijgt: '
            .'**A** (laag risico, som ≤ 6), **B** (middelhoog, som ≤ 9), of **C** '
            .'(hoog risico, som > 9). Deze classificatie stuurt de rest van de '
            .'aanvraag — welke extra vragen er gesteld worden en hoe grondig de '
            .'behandelaar toetst — dus moet de optelling exact kloppen.';
    }

    /**
     * Alle 14 antwoorden die samen de som bepalen. Per scenario zetten we een
     * specifieke combinatie en verwachten we A/B/C als uitkomst.
     *
     * Waarden staan als string omdat OF's Radio-component de option-value
     * als string opslaat in de submission-state. De transpiler cast ze later
     * naar float voor de optelling, maar de `!!`-trigger moet wel JS-truthy-
     * correct zijn — waar `"0"` als truthy telt en `""` als falsy.
     *
     * @return array<string, string>
     */
    private static function minimaleAntwoorden(): array
    {
        // Som = 0.5+0.25+0+0.25+0+0.5+0+0+0+0.25+0.25+0.25+0+0.5 = 2.75 → A
        return [
            'watIsDeAantrekkingskrachtVanHetEvenement' => '0.5',            // Wijk of buurt
            'watIsDeBelangrijksteLeeftijdscategorieVanDeDoelgroep' => '0.25', // 0-15 met begeleiding
            'isErSprakeVanZanwezigheidVanPolitiekeAandachtEnOfMediageniekheid' => '0', // Nee
            'isEenDeelVanDeDoelgroepVerminderdZelfredzaam' => '0.25',       // Voldoende zelfredzaam
            'isErSprakeVanAanwezigheidVanRisicovolleActiviteiten' => '0',    // Nee
            'watIsHetGrootsteDeelVanDeSamenstellingVanDeDoelgroep' => '0.5', // Alleen toeschouwers
            'isErSprakeVanOvernachten' => '0',                               // Niet overnacht
            'isErGebruikVanAlcoholEnDrugs' => '0',                          // Niet aanwezig
            'watIsHetAantalGelijktijdigAanwezigPersonen' => '0',            // Minder dan 150
            'inWelkSeizoenVindtHetEvenementPlaats' => '0.25',               // Lente of herfst
            'inWelkeLocatieVindtHetEvenementPlaats' => '0.25',              // Ingericht gebouw
            'opWelkSoortOndergrondVindtHetEvenementPlaats' => '0.25',       // Verhard
            'watIsDeTijdsduurVanHetEvenement' => '0',                       // < 3u daguren
            'welkeBeschikbaarheidVanAanEnAfvoerwegenIsVanToepassing' => '0.5', // Redelijke wegen
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function all(): array
    {
        // Baseline → som = 2.5 → classificatie A
        $baselineA = self::minimaleAntwoorden();

        // Voor B: tel een paar velden op tot som ligt tussen 6 en 9.
        $baselineB = $baselineA;
        $baselineB['watIsDeAantrekkingskrachtVanHetEvenement'] = '1.5';      // +1
        $baselineB['isErSprakeVanZanwezigheidVanPolitiekeAandachtEnOfMediageniekheid'] = '1'; // +1
        $baselineB['isErSprakeVanAanwezigheidVanRisicovolleActiviteiten'] = '1'; // +1
        $baselineB['isErGebruikVanAlcoholEnDrugs'] = '1';                    // +1
        $baselineB['watIsHetAantalGelijktijdigAanwezigPersonen'] = '0.5';    // +0.5
        // Som: 2.75 + 4.5 = 7.25 → B

        // Voor C: nog meer velden naar max.
        $baselineC = $baselineB;
        $baselineC['isEenDeelVanDeDoelgroepVerminderdZelfredzaam'] = '1';     // +0.75
        $baselineC['watIsHetGrootsteDeelVanDeSamenstellingVanDeDoelgroep'] = '1'; // +0.5
        $baselineC['isErSprakeVanOvernachten'] = '1';                         // +1
        $baselineC['inWelkeLocatieVindtHetEvenementPlaats'] = '0.75';         // +0.5
        $baselineC['welkeBeschikbaarheidVanAanEnAfvoerwegenIsVanToepassing'] = '1'; // +0.5
        // Som: 7.25 + 3.25 = 10.5 → C

        return [
            'Laag risico — alle antwoorden gunstig → classificatie A' => [[
                'naam' => 'Laag risico (A): minimale-risico-antwoorden bij elke vraag',
                'omschrijving' => 'Als een organisator de minst-risicovolle antwoorden geeft op alle 14 vragen '
                    .'(kleine doelgroep, bekende locatie, overdag, geen alcohol/drugs, etc.), dan '
                    .'moet het evenement worden geclassificeerd als A — laag risico. Dat betekent '
                    .'dat de vervolgvragen beperkt blijven en de behandelaar een lichte toets kan '
                    .'uitvoeren.',
                'categorie' => 'computation',
                'stap' => self::STAP_RISICOSCAN,
                'trigger_velden' => array_keys($baselineA),
                'gegeven' => $baselineA,
                'verwacht' => [
                    'risicoClassificatie' => 'A',
                ],
            ]],

            'Middelhoog risico — mix van ja/nee-risicos → classificatie B' => [[
                'naam' => 'Middelhoog risico (B): gemeentelijk evenement met alcohol en risicovolle activiteiten',
                'omschrijving' => 'Een gemeentelijk evenement met politieke aandacht, risicovolle activiteiten, '
                    .'alcoholgebruik en 150-2000 bezoekers zit in het midden van de risico-range. '
                    .'De som ligt tussen 6 en 9, dus classificatie B — middelhoog risico. De '
                    .'behandelaar stelt dan aanvullende vragen over maatregelen.',
                'categorie' => 'computation',
                'stap' => self::STAP_RISICOSCAN,
                'trigger_velden' => array_keys($baselineB),
                'gegeven' => $baselineB,
                'verwacht' => [
                    'risicoClassificatie' => 'B',
                ],
            ]],

            'Hoog risico — veel gevaarfactoren → classificatie C' => [[
                'naam' => 'Hoog risico (C): grote doelgroep met verminderd-zelfredzame bezoekers en overnachting',
                'omschrijving' => 'Wanneer er meerdere risico-factoren samenkomen — een grote doelgroep met '
                    .'verminderd zelfredzame bezoekers, overnachting buiten een daarvoor ingerichte '
                    .'locatie, en slechte aan- en afvoerwegen — tilt de som het evenement boven de '
                    .'drempel van 9. Classificatie C betekent dat het een hoog-risico evenement is '
                    .'en de volle behandelaar-toets met maximum aan vervolgvragen in werking treedt.',
                'categorie' => 'computation',
                'stap' => self::STAP_RISICOSCAN,
                'trigger_velden' => array_keys($baselineC),
                'gegeven' => $baselineC,
                'verwacht' => [
                    'risicoClassificatie' => 'C',
                ],
            ]],
        ];
    }
}
