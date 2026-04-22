<?php

declare(strict_types=1);

/**
 * Verzamelt alle ScenarioProvider-classes in deze directory en draait ze.
 *
 * Op deze manier hoef je bij het toevoegen van een nieuwe scenario-provider
 * geen aparte test-file meer te maken: ga naar `Scenarios/`, maak een klasse
 * die `ScenarioProvider` implementeert, en `php artisan test` pakt 'em mee.
 */

use Tests\Feature\EventForm\Equivalence\EquivalenceScenario;
use Tests\Feature\EventForm\Equivalence\Scenarios\ScenarioProvider;

/**
 * @return array<string, array<int, array<string, mixed>>>
 */
function alleScenariosViaProviders(): array
{
    $dir = __DIR__.'/Scenarios';
    $all = [];
    foreach (glob($dir.'/*.php') ?: [] as $file) {
        $basename = basename($file, '.php');
        if ($basename === 'ScenarioProvider') {
            continue;
        }
        $fqcn = 'Tests\\Feature\\EventForm\\Equivalence\\Scenarios\\'.$basename;
        if (! class_exists($fqcn)) {
            continue;
        }
        $reflection = new ReflectionClass($fqcn);
        if (! $reflection->implementsInterface(ScenarioProvider::class) || $reflection->isAbstract()) {
            continue;
        }
        foreach ($fqcn::all() as $label => $entry) {
            // Unique key per scenario om Pest-dataset-naam-conflicten te vermijden
            $all[$basename.' — '.$label] = $entry;
        }
    }

    return $all;
}

test(
    'Equivalentie-scenario volgt OF-gedrag: {0.naam}',
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
)->with(alleScenariosViaProviders());
