<?php

declare(strict_types=1);

/**
 * Gedrags-equivalentietest: registratie-backend per gemeente + aanvraagsoort.
 *
 * De scenario-data + uitleg staan in `Scenarios\RegistrationBackendScenarios`
 * zodat zowel deze test als het `eventform:gedrags-rapport`-command ze delen.
 * Zie die class voor de volledige context van wat hier getest wordt.
 */

use Tests\Feature\EventForm\Equivalence\EquivalenceScenario;
use Tests\Feature\EventForm\Equivalence\Scenarios\RegistrationBackendScenarios;

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
)->with(RegistrationBackendScenarios::all());
