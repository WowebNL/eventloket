<?php

declare(strict_types=1);

namespace Tests\Feature\EventForm\Equivalence;

use App\EventForm\Rules\RulesEngine;
use App\EventForm\State\FormState;
use Illuminate\Support\Arr;

/**
 * Draait één equivalentie-scenario: zet de "gegeven"-state op, laat de
 * RulesEngine z'n ding doen, en vergelijkt het resultaat met de
 * verwachting.
 *
 * Deze helper is bewust agnostisch over hoe rules intern uitgevoerd
 * worden. Nu draait 'ie tegen de getranspileerde RulesEngine; straks,
 * als we naar de clean Filament-native versie migreren, kan dezelfde
 * helper (of z'n tegenhanger) tegen die implementatie draaien. Zolang
 * dezelfde scenarios door beide komen, is het gedrag equivalent.
 *
 * Scenario-shape:
 *  - 'naam' (string): korte benaming zoals in test-output getoond
 *  - 'omschrijving' (string): wat + waarom in 1-3 zinnen Nederlands
 *  - 'gegeven' (array): beginwaarden in FormState, dot-notation toegestaan
 *  - 'verwacht' (array): key → verwachte waarde na rule-evaluatie. Ondersteunt
 *    namespaces voor niet-veld-output:
 *      • 'system.<key>' → `$state->get('system.<key>')` via system-bag
 *      • 'field_hidden.<key>' → `$state->isFieldHidden('<key>')`
 *      • 'step_applicable.<uuid>' → `$state->isStepApplicable('<uuid>')`
 *      • anders → `$state->get('<key>')` (gewone field/variable)
 */
final class EquivalenceScenario
{
    /**
     * @param  array<string, mixed>  $scenario
     * @return array<string, array{expected: mixed, actual: mixed}>  lege array = alles gelijk; anders per afwijking
     */
    public static function run(array $scenario): array
    {
        $state = FormState::empty();

        // 'gegeven' zet initial fields/variables. Dot-paden worden
        // genest: 'evenementInGemeente.brk_identification' = 'GM…'
        // wordt ['evenementInGemeente' => ['brk_identification' => 'GM…']].
        $initial = [];
        foreach (($scenario['gegeven'] ?? []) as $path => $value) {
            Arr::set($initial, $path, $value);
        }
        foreach ($initial as $key => $value) {
            $state->setField($key, $value);
        }

        app(RulesEngine::class)->evaluate($state);

        $diffs = [];
        foreach (($scenario['verwacht'] ?? []) as $path => $expected) {
            $actual = self::readExpectation($state, $path);
            if ($actual !== $expected) {
                $diffs[$path] = ['expected' => $expected, 'actual' => $actual];
            }
        }

        return $diffs;
    }

    private static function readExpectation(FormState $state, string $path): mixed
    {
        if (str_starts_with($path, 'system.')) {
            // `setSystem('registration_backend', X)` zet `$this->system['registration_backend']`;
            // FormState::get() descend-logica heeft geen aparte 'system.'-prefix-handling,
            // dus we lezen direct uit de snapshot. Dot-access na de eerste prefix ondersteunen
            // we via Arr::get zodat bv. 'system.auth.user.id' werkt als system[auth] nested is.
            $systemBag = $state->toSnapshot()['system'] ?? [];

            return \Illuminate\Support\Arr::get($systemBag, substr($path, strlen('system.')));
        }
        if (str_starts_with($path, 'field_hidden.')) {
            return $state->isFieldHidden(substr($path, strlen('field_hidden.')));
        }
        if (str_starts_with($path, 'step_applicable.')) {
            return $state->isStepApplicable(substr($path, strlen('step_applicable.')));
        }

        return $state->get($path);
    }
}
