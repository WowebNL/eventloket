<?php

declare(strict_types=1);

namespace Tests\Feature\EventForm\Equivalence;

use App\EventForm\Rules\RulesEngine;
use App\EventForm\State\FormState;
use App\Filament\Organiser\Pages\EventFormPage;
use Illuminate\Support\Arr;
use Livewire\Livewire;

/**
 * Draait één equivalentie-scenario en vergelijkt het resultaat met de
 * verwachting. Er zijn twee runner-modi — beide geven dezelfde soort output
 * (diff-array). Welke je kiest hangt ervan af wat je wilt testen:
 *
 *  - `run($scenario)`
 *    Lichte mode. Bouwt een losse FormState, draait de RulesEngine, checkt
 *    de state. Snel, geen DB of user-context nodig. Dekt rule-level gedrag.
 *
 *  - `runViaLivewire($scenario)`
 *    Volledige mode. Mount de echte EventFormPage via Livewire (user + tenant
 *    moeten aanwezig zijn in de test-setup), past scenario-waarden toe, en
 *    laat de hele lifecycle draaien inclusief Filament's `->visible()`-
 *    closures op componenten. Dekt zowel rule-level als component-level
 *    conditionele zichtbaarheid — dat is waar het formulier echt gedrag
 *    vandaan haalt in de browser.
 *
 * Scenario-shape (identiek voor beide runners):
 *  - 'naam'          (string)       1-regel titel
 *  - 'omschrijving'  (string)       1-3 zinnen uitleg
 *  - 'gegeven'       (array)        initiële waarden (dot-notation ondersteund)
 *  - 'verwacht'      (array)        key → verwachte waarde na evaluatie
 *      • 'system.<key>'           → state system-bag
 *      • 'field_hidden.<key>'     → state->isFieldHidden()
 *      • 'step_applicable.<uuid>' → state->isStepApplicable()
 *      • anders                   → state->get() (field of variable)
 */
final class EquivalenceScenario
{
    /**
     * Lichte runner: direct tegen RulesEngine + FormState.
     *
     * @param  array<string, mixed>  $scenario
     * @return array<string, array{expected: mixed, actual: mixed}>
     */
    public static function run(array $scenario): array
    {
        $state = FormState::empty();
        self::seedState($state, $scenario['gegeven'] ?? []);
        app(RulesEngine::class)->evaluate($state);

        // `field_visible.*`-verwachtingen vereisen rendered HTML, wat deze
        // lichte runner niet levert. We filteren ze weg en laten ze over
        // aan `runViaLivewire()` (voor de test-suite) + json-logic-js
        // (voor de spec-referentie in het rapport).
        $verwacht = array_filter(
            $scenario['verwacht'] ?? [],
            static fn ($_, string $path): bool => ! str_starts_with($path, 'field_visible.'),
            ARRAY_FILTER_USE_BOTH,
        );

        return self::diff($state, $verwacht);
    }

    /**
     * Volledige runner: mount de echte Livewire EventFormPage. Vereist dat
     * de test-context al een authenticated user + Filament-tenant heeft.
     *
     * Naast state-gebaseerde verwachtingen (field_hidden/step_applicable/...)
     * ondersteunt deze runner ook `field_visible.<key>` — dat checkt of het
     * veld daadwerkelijk in de rendered HTML zichtbaar is. Dat dekt ook de
     * component-level conditionals die via Filament's `->visible()`/`->hidden()`
     * closures gaan en niet in FormState terechtkomen.
     *
     * @param  array<string, mixed>  $scenario
     * @return array<string, array{expected: mixed, actual: mixed}>
     */
    public static function runViaLivewire(array $scenario): array
    {
        $test = Livewire::test(EventFormPage::class);

        /** @var EventFormPage $page */
        $page = $test->instance();
        $state = $page->state();

        // Mount heeft al prefill + eventloketSession gefetched. Op die
        // initial-state passen we de scenario-waarden toe — zodat de
        // gegeven waarden eventuele prefill kunnen overschrijven.
        self::seedState($state, $scenario['gegeven'] ?? []);

        // Gegeven-waarden die op Filament-veld-paden matchen, ook in
        // `data` zetten. Dat is nodig voor component-conditionals: die
        // lezen via `Get $get` uit Filament's form-state, niet uit onze
        // FormState. Alle root-keys die scalair zijn kunnen direct;
        // geneste arrays (zoals `evenementInGemeente.brk_identification`)
        // blijven puur in FormState omdat ze niet via user-input komen.
        $data = $page->data ?? [];
        foreach ($scenario['gegeven'] ?? [] as $path => $value) {
            if (! str_contains($path, '.')) {
                $data[$path] = $value;
            }
        }
        $test->set('data', $data);

        // Run de rules-engine expliciet na de overrides. In productie
        // gebeurt dat via `updated()`-hook bij form-wijzigingen; hier
        // forceren we 'em direct zodat de state reflecteert wat er zou
        // gebeuren als de user deze waarden live had ingevuld.
        app(RulesEngine::class)->evaluate($state);

        // Voor render-verwachtingen (`field_visible.*`) moeten we de
        // rendered HTML van de component pakken nadat alle state is
        // geseed. Livewire's test-harness levert die als string.
        $html = null;
        if (self::needsRenderedHtml($scenario['verwacht'] ?? [])) {
            $html = $test->html();
        }

        return self::diff($state, $scenario['verwacht'] ?? [], $html);
    }

    /** @param  array<string, mixed>  $verwacht */
    private static function needsRenderedHtml(array $verwacht): bool
    {
        foreach (array_keys($verwacht) as $path) {
            if (str_starts_with($path, 'field_visible.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Zet gegeven-waarden in state. Dot-paden worden genest: bv.
     * `evenementInGemeente.brk_identification` = 'GM…' wordt
     * `['evenementInGemeente' => ['brk_identification' => 'GM…']]`.
     *
     * @param  array<string, mixed>  $gegeven
     */
    private static function seedState(FormState $state, array $gegeven): void
    {
        $nested = [];
        foreach ($gegeven as $path => $value) {
            Arr::set($nested, $path, $value);
        }
        foreach ($nested as $rootKey => $rootValue) {
            // Als root-key al bestaat en zowel oude als nieuwe waarde array zijn,
            // mergen we zodat prefill-waarden overleven naast scenario-overrides.
            $existing = $state->get($rootKey);
            if (is_array($existing) && is_array($rootValue)) {
                $state->setField($rootKey, array_replace_recursive($existing, $rootValue));
            } else {
                $state->setField($rootKey, $rootValue);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $verwacht
     * @return array<string, array{expected: mixed, actual: mixed}>
     */
    private static function diff(FormState $state, array $verwacht, ?string $html = null): array
    {
        $diffs = [];
        foreach ($verwacht as $path => $expected) {
            $actual = self::readExpectation($state, $path, $html);
            if ($actual !== $expected) {
                $diffs[$path] = ['expected' => $expected, 'actual' => $actual];
            }
        }

        return $diffs;
    }

    private static function readExpectation(FormState $state, string $path, ?string $html = null): mixed
    {
        if (str_starts_with($path, 'system.')) {
            $systemBag = $state->toSnapshot()['system'] ?? [];

            return Arr::get($systemBag, substr($path, strlen('system.')));
        }
        if (str_starts_with($path, 'field_hidden.')) {
            return $state->isFieldHidden(substr($path, strlen('field_hidden.')));
        }
        if (str_starts_with($path, 'step_applicable.')) {
            return $state->isStepApplicable(substr($path, strlen('step_applicable.')));
        }
        if (str_starts_with($path, 'field_visible.')) {
            // Filament rendert een veld met z'n key in verschillende
            // attributes: `wire:model="data.X"` voor text-inputs,
            // `data-field-wrapper-id="X"` op het wrapper-element, en
            // `id="…-X"` op de input zelf. Eén treffer is genoeg om te
            // bevestigen dat het veld in de rendered output zit. Is het
            // via ->hidden() of ->visible(false) weggelaten, dan zit
            // geen enkele van die markers in de HTML.
            if ($html === null) {
                return null;
            }
            $key = substr($path, strlen('field_visible.'));

            return str_contains($html, 'wire:model="data.'.$key.'"')
                || str_contains($html, 'data.'.$key.'"')
                || str_contains($html, '"'.$key.'"')
                || str_contains($html, 'fi-fo-field-wrp-'.$key);
        }

        return $state->get($path);
    }
}
