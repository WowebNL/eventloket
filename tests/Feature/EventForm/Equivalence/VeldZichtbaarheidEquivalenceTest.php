<?php

declare(strict_types=1);

/**
 * Gedrags-equivalentietest: velden en stappen die zichtbaar worden op basis
 * van keuzes elders in het formulier.
 *
 * De scenario-data + uitleg staan in `Scenarios\VeldZichtbaarheidScenarios`
 * zodat zowel deze test als het `eventform:gedrags-rapport`-command ze delen.
 */

use Tests\Feature\EventForm\Equivalence\EquivalenceScenario;
use Tests\Feature\EventForm\Equivalence\Scenarios\VeldZichtbaarheidScenarios;

test(
    'Veld-zichtbaarheid volgt de gebruikers-keuzes zoals in Open Forms: {0.naam}',
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
)->with(VeldZichtbaarheidScenarios::all());
