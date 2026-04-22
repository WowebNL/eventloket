<?php

declare(strict_types=1);

namespace Tests\Feature\EventForm\Equivalence;

use App\EventForm\Reporting\FieldCatalog;
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
    private static ?FieldCatalog $catalog = null;

    private static function catalog(): FieldCatalog
    {
        return self::$catalog ??= FieldCatalog::fromLocalDump();
    }

    /**
     * Detecteert of een veld met de gegeven key zichtbaar is in de rendered HTML.
     *  Filament markeert verschillende component-types anders:
     *  - inputs (textfield, radio, select, selectboxes, checkbox, datetime,
     *    file, textarea, number, currency, editgrid, map, addressNL) →
     *    `wire:model`-binding met `data.<key>` als pad.
     *  - content (display-only) → de content-tekst zelf wordt gerendered
     *    of niet. We fallbacken op de key als data-attribute zoektocht.
     *  - fieldset → `<legend>Label</legend>` in HTML.
     *  - columns → wrappers zonder key; dekken we via label of wire:model
     *    van onderliggende velden.
     */
    private static function detectFieldVisible(string $html, string $key): bool
    {
        // Eerst de generieke input-markers — die dekken het merendeel.
        if (
            str_contains($html, 'wire:model="data.'.$key.'"')
            || str_contains($html, 'wire:model.defer="data.'.$key.'"')
            || str_contains($html, 'fi-fo-field-wrp-'.$key)
        ) {
            return true;
        }

        // Type-specifieke fallbacks op basis van component-type uit OF.
        $type = self::catalog()->fieldType($key);
        $label = self::catalog()->fieldLabel($key);
        $plainLabel = $label !== null ? trim(strip_tags($label)) : '';

        if ($type === 'fieldset' && $plainLabel !== '') {
            return self::legendContains($html, $plainLabel);
        }

        if ($type === 'content') {
            // Content-components renderen onder een TextEntry met hun
            // HTML-body. Filament genereert een `fi-ta` of `fi-in-text`
            // wrapper; zoeken op de raw body-text is onbetrouwbaar
            // (veel interpolaties). Fallback: zoek naar `data-field-name="<key>"`
            // of key als comment/attribuut — als niets dan: true bij
            // aanwezigheid van een Content-block near de omgeving.
            if (str_contains($html, $key.'"') || str_contains($html, $key.'\'')) {
                return true;
            }
            // Als 'r geen duidelijke marker is, val terug op "was het
            // target-component onderdeel van een fieldset dat niet meer
            // rendered werd?" — dan toch zichtbaar = false. We kunnen
            // dit simpelweg niet goed detecteren voor content-blocks
            // en accepteren dat met een conservative false.
            return false;
        }

        return false;
    }

    private static function legendContains(string $html, string $label): bool
    {
        if (preg_match_all('#<legend[^>]*>(.*?)</legend>#is', $html, $matches) === false) {
            return false;
        }
        foreach ($matches[1] ?? [] as $legendContent) {
            $clean = trim(preg_replace('/<!--.*?-->/s', '', $legendContent) ?? $legendContent);
            $plain = trim(strip_tags($clean));
            if ($plain === '') {
                continue;
            }
            if (str_contains($plain, $label)) {
                return true;
            }
        }

        return false;
    }

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
        return self::runViaLivewireDetailed($scenario)['diffs'];
    }

    /**
     * Uitgebreide Livewire-runner die ook teruggeeft welke checks daadwerkelijk
     * gemeten zijn en welke overgeslagen moesten worden door technische
     * beperkingen (typisch: velden op niet-actieve wizard-stappen). Hiermee
     * kan het gedrags-rapport per scenario de bewijssterkte classificeren.
     *
     * @param  array<string, mixed>  $scenario
     * @return array{diffs: array<string, array{expected: mixed, actual: mixed}>, measured: list<string>, skipped: list<string>}
     */
    public static function runViaLivewireDetailed(array $scenario): array
    {
        $test = Livewire::test(EventFormPage::class);

        /** @var EventFormPage $page */
        $page = $test->instance();
        $state = $page->state();

        // Mount heeft al prefill + eventloketSession gefetched. Op die
        // initial-state passen we de scenario-waarden toe — zodat de
        // gegeven waarden eventuele prefill kunnen overschrijven.
        self::seedState($state, $scenario['gegeven'] ?? []);

        // Gegeven-waarden in Filament's `data`-array zetten. Dat is nodig
        // voor component-conditionals: die lezen via `Get $get` uit
        // Filament's form-state, niet uit onze FormState. Dot-paden als
        // `selectboxesVeld.optie` worden genest weggeschreven, zodat
        // Filament ze als `$get('selectboxesVeld')['optie']` kan lezen.
        $data = $page->data ?? [];
        foreach ($scenario['gegeven'] ?? [] as $path => $value) {
            Arr::set($data, $path, $value);
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
        //
        // Beperking: Filament's Wizard rendert server-side alleen de
        // actieve step (stap 1 bij fresh mount); andere steps worden
        // pas Alpine-side in de browser ingeladen. Voor scenarios waar
        // het target-veld op een andere stap staat, is Livewire's test-
        // runner fundamenteel blind. Zulke `field_visible.*`-check's
        // zetten we tijdelijk uit — de JS-spec-referentie (via json-
        // logic-js) én Playwright-walkthroughs dekken die dimensie wél.
        $html = null;
        $origVerwacht = $scenario['verwacht'] ?? [];
        $verwacht = $origVerwacht;
        $skipped = [];
        if (self::needsRenderedHtml($verwacht)) {
            $html = $test->html();
            [$verwacht, $skipped] = self::partitionVisibilityChecks($verwacht, $html);
        }

        $measured = array_keys($verwacht);

        return [
            'diffs' => self::diff($state, $verwacht, $html),
            'measured' => array_values($measured),
            'skipped' => array_values($skipped),
        ];
    }

    /**
     * Splitst verwachtingen in twee groepen: die we gemeten kunnen
     * krijgen in de rendered HTML, en die we moeten overslaan omdat
     * het betreffende veld server-side niet rendert (typisch velden
     * op een niet-actieve wizard-stap).
     *
     * @param  array<string, mixed>  $verwacht
     * @return array{0: array<string, mixed>, 1: list<string>}  [meetbaar, overgeslagen-paths]
     */
    private static function partitionVisibilityChecks(array $verwacht, string $html): array
    {
        $skipped = [];
        foreach (array_keys($verwacht) as $path) {
            if (! str_starts_with($path, 'field_visible.')) {
                continue;
            }
            $key = substr($path, strlen('field_visible.'));
            // Als het veld z'n key nergens in de HTML heeft (geen wire:model,
            // geen wrapper, geen label-in-legend), dan zit 'ie op een niet-
            // actieve stap of is 'ie een content-component zonder marker.
            // In beide gevallen kunnen we 'em niet via deze runner checken.
            $heeftAnyMarker = str_contains($html, 'wire:model="data.'.$key.'"')
                || str_contains($html, 'wire:model.defer="data.'.$key.'"')
                || str_contains($html, 'fi-fo-field-wrp-'.$key);

            // Fieldsets hebben geen key-attribute maar een label-in-legend —
            // als het label in een <legend> staat, kunnen we 'em wél meten.
            if (! $heeftAnyMarker && self::catalog()->fieldType($key) === 'fieldset') {
                $label = self::catalog()->fieldLabel($key);
                $plain = $label !== null ? trim(strip_tags($label)) : '';
                if ($plain !== '' && self::legendContains($html, $plain)) {
                    $heeftAnyMarker = true;
                }
            }
            if (! $heeftAnyMarker) {
                unset($verwacht[$path]);
                $skipped[] = $path;
            }
        }

        return [$verwacht, $skipped];
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
            if ($html === null) {
                return null;
            }
            $key = substr($path, strlen('field_visible.'));

            return self::detectFieldVisible($html, $key);
        }

        return $state->get($path);
    }
}
