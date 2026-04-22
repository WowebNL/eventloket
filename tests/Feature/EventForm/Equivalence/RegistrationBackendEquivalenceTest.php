<?php

declare(strict_types=1);

/**
 * Gedrags-equivalentietest: registratie-backend per gemeente + aanvraagsoort.
 *
 * ─── Wat wordt hier getest? ──────────────────────────────────────────────
 * Eventloket routeert elke nieuwe zaak naar één van 45 registratie-backends.
 * Welke backend je krijgt hangt af van twee dingen:
 *
 *   1. Welke gemeente het evenement betreft (herkend via de BRK-code, bv.
 *      GM0899 = Maastricht)
 *   2. Wat voor soort aanvraag de organisator doet:
 *      - een volledige vergunningaanvraag
 *      - een vooraankondiging (alleen melden dat er iets aankomt)
 *      - een melding (geen weg-afsluiting, lichter regime)
 *
 * 15 deelnemende gemeentes × 3 aanvraagsoorten = 45 backend-configuraties.
 *
 * Een fout in deze routering betekent dat zaken in het verkeerde doel-systeem
 * terechtkomen. Daarom willen we exact hetzelfde gedrag als in Open Forms —
 * geen enkele gemeente/aanvraag-combinatie mag afwijken.
 *
 * ─── Hoe werkt deze test? ───────────────────────────────────────────────
 * We draaien elk van de 45 combinaties door de RulesEngine heen en checken
 * welke backend-waarde in de state-system-bag is gezet. Deze tests staan
 * los van de huidige implementatie — ze vergelijken "gegeven input X, na
 * rule-evaluatie moet output Y zijn". Zodra we migreren naar een
 * handgeschreven Filament-versie (zonder RulesEngine) moeten dezelfde
 * scenario's nog steeds groen blijven.
 */

use Tests\Feature\EventForm\Equivalence\EquivalenceScenario;

/**
 * De gemeente × aanvraag-pivot zoals deze in Open Forms is geconfigureerd
 * (geëxtraheerd uit de transpiled rules op 22 april 2026). Dit is de
 * "waarheid" — wijzig alleen bij aantoonbare aanpassing aan OF-config.
 *
 * Structuur:
 *   BRK-code → aanvraagsoort → backend-id
 *
 * De drie aanvraagsoort-triggers zijn:
 *   'isVergunningaanvraag = true' → vergunningaanvraag
 *   'waarvoorWiltUEventloketGebruiken = "vooraankondiging"' → vooraankondiging
 *   'wordenErGebiedsontsluitingswegen… = "Nee"' → melding
 */
const PIVOT = [
    'GM0882' => ['vergunning' => 'backend23', 'vooraankondiging' => 'backend22', 'melding' => 'backend24'],
    'GM0888' => ['vergunning' => 'backend3',  'vooraankondiging' => 'backend9',  'melding' => 'backend8'],
    'GM0899' => ['vergunning' => 'backend15', 'vooraankondiging' => 'backend14', 'melding' => 'backend13'],
    'GM0917' => ['vergunning' => 'backend1',  'vooraankondiging' => 'backend4',  'melding' => 'backend6'],
    'GM0928' => ['vergunning' => 'backend21', 'vooraankondiging' => 'backend20', 'melding' => 'backend19'],
    'GM0938' => ['vergunning' => 'backend26', 'vooraankondiging' => 'backend25', 'melding' => 'backend27'],
    'GM0965' => ['vergunning' => 'backend29', 'vooraankondiging' => 'backend28', 'melding' => 'backend30'],
    'GM0971' => ['vergunning' => 'backend35', 'vooraankondiging' => 'backend34', 'melding' => 'backend36'],
    'GM0981' => ['vergunning' => 'backend38', 'vooraankondiging' => 'backend37', 'melding' => 'backend39'],
    'GM0986' => ['vergunning' => 'backend44', 'vooraankondiging' => 'backend43', 'melding' => 'backend45'],
    'GM0994' => ['vergunning' => 'backend41', 'vooraankondiging' => 'backend40', 'melding' => 'backend42'],
    'GM1729' => ['vergunning' => 'backend2',  'vooraankondiging' => 'backend5',  'melding' => 'backend7'],
    'GM1883' => ['vergunning' => 'backend32', 'vooraankondiging' => 'backend31', 'melding' => 'backend33'],
    'GM1903' => ['vergunning' => 'backend18', 'vooraankondiging' => 'backend17', 'melding' => 'backend16'],
    'GM1954' => ['vergunning' => 'backend10', 'vooraankondiging' => 'backend12', 'melding' => 'backend11'],
];

/**
 * Bouw een dataset-provider voor Pest. Elk tuple beschrijft één
 * (gemeente, aanvraagsoort) → backend-id combinatie in mensentaal.
 */
$buildScenarios = function (): array {
    $scenarios = [];
    $triggerBySoort = [
        'vergunning' => ['isVergunningaanvraag' => true],
        'vooraankondiging' => ['waarvoorWiltUEventloketGebruiken' => 'vooraankondiging'],
        'melding' => ['wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee'],
    ];
    $soortOmschrijving = [
        'vergunning' => 'vergunningaanvraag (volledige evenementenvergunning)',
        'vooraankondiging' => 'vooraankondiging (alleen aankondiging, nog geen vergunning)',
        'melding' => 'melding (lichter regime, geen wegafsluiting)',
    ];

    foreach (PIVOT as $gemeente => $backends) {
        foreach ($backends as $soort => $backend) {
            $label = "{$gemeente} + {$soort} → {$backend}";
            $scenarios[$label] = [[
                'naam' => $label,
                'omschrijving' => "Voor gemeente {$gemeente} bij een {$soortOmschrijving[$soort]} moet het systeem de zaak naar registratie-backend '{$backend}' routeren.",
                'gegeven' => array_merge(
                    ['evenementInGemeente.brk_identification' => $gemeente],
                    $triggerBySoort[$soort],
                ),
                'verwacht' => [
                    'system.registration_backend' => $backend,
                ],
            ]];
        }
    }

    return $scenarios;
};

test(
    'Bij gemeente+aanvraagsoort wordt de zaak naar het juiste registratie-backend gerouteerd: {0.naam}',
    function (array $scenario) {
        $diffs = EquivalenceScenario::run($scenario);

        expect($diffs)->toBe(
            [],
            sprintf(
                "Scenario faalt — %s\n\nOmschrijving: %s\n\nAfwijkingen: %s",
                $scenario['naam'],
                $scenario['omschrijving'],
                json_encode($diffs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ),
        );
    },
)->with($buildScenarios());
