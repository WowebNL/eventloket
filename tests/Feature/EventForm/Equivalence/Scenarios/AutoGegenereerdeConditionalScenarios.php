<?php

declare(strict_types=1);

namespace Tests\Feature\EventForm\Equivalence\Scenarios;

/**
 * Auto-gegenereerde scenarios: voor elke component-level `conditional.when`
 * in de OF-JSON bouwen we twee scenarios — één die de match-case test
 * (target zichtbaar/verborgen volgens `show`), en één die de negatie test.
 *
 * Dit dekt de gedragskant van het formulier die niet via logic-rules gaat
 * maar via Filament's `->visible()`-closures op individuele velden. Samen
 * met de handmatige rule-scenarios krijgen we zo alle conditionele
 * zichtbaarheid in het formulier getest.
 *
 * Waarom auto-generated: deze scenarios zijn mechanisch af te leiden uit
 * de OF-configuratie. Handmatig schrijven zou ruim 100 bijna-identieke
 * scenario-files opleveren. Bij iedere OF-aanpassing lopen ze automatisch
 * mee zodra `transpile:event-form` nieuwe data inleest.
 *
 * Stap 3 (Locatie) is bewust uitgesloten — dat pakken we later aan omdat
 * de kaart-component + route-logica extra speciale behandeling krijgt.
 */
final class AutoGegenereerdeConditionalScenarios implements ScenarioProvider
{
    /**
     * Stap-UUID van Locatie — scenarios op deze pagina genereren we (nog)
     * niet automatisch; die pagina pakt later een handmatige aanpak.
     */
    private const STAP_LOCATIE = '2186344f-9821-45d1-bd52-9900ae15fcb6';

    /**
     * Een string die gegarandeerd géén match-waarde zal zijn voor welke
     * OF-conditional dan ook. We plakken er een uuid-achtige-suffix aan
     * om collisie extreem onwaarschijnlijk te maken.
     */
    private const NO_MATCH_MARKER = '___no_match_value_f7e3b2___';

    public static function categorie(): string
    {
        return 'visibility';
    }

    public static function kop(): string
    {
        return 'Component-level conditionele zichtbaarheid (auto-gegenereerd)';
    }

    public static function inleiding(): string
    {
        return 'Voor elk veld in het formulier dat alleen onder een specifieke voorwaarde '
            .'zichtbaar is, genereren we twee scenarios: één waarin aan de voorwaarde '
            .'wordt voldaan (en we verwachten dat het veld zichtbaar is), en één waarin '
            .'de voorwaarde NIET is voldaan (en we verwachten dat het veld verborgen is). '
            .'Zo wordt elke conditionele regel in het formulier in twee richtingen '
            .'bewezen.';
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    /**
     * @var array<string, string>  veld-key → component-type, globaal over alle stappen
     */
    private static array $fieldTypeCache = [];

    public static function all(): array
    {
        // Pest evaluating datasets tijdens test-discovery; `base_path()` is
        // daar niet altijd beschikbaar. Relatieve path vanaf deze file is
        // robuuster: __DIR__ ↑↑↑↑↑ = project-root.
        $path = __DIR__.'/../../../../../docker/local-data/open-formulier/formSteps.json';
        if (! is_file($path)) {
            return [];
        }
        $raw = json_decode((string) file_get_contents($path), true);
        if (! is_array($raw)) {
            return [];
        }
        $steps = is_array($raw['results'] ?? null) ? $raw['results'] : $raw;
        if (! is_array($steps)) {
            return [];
        }

        // Eerste pass: bouw een globale field-key → type index zodat we
        // bij een conditional.when op een selectboxes-veld de juiste
        // data-shape genereren (`{optie: true}` i.p.v. string).
        self::$fieldTypeCache = [];
        foreach ($steps as $step) {
            if (! is_array($step)) continue;
            $components = $step['configuration']['components'] ?? [];
            if (is_array($components)) {
                /** @var list<array<string, mixed>> $components */
                self::indexFieldTypes($components);
            }
        }

        $scenarios = [];
        foreach ($steps as $step) {
            if (! is_array($step)) {
                continue;
            }
            $stapUuid = (string) ($step['uuid'] ?? '');
            if ($stapUuid === '' || $stapUuid === self::STAP_LOCATIE) {
                continue;
            }
            $components = $step['configuration']['components'] ?? [];
            if (is_array($components)) {
                /** @var list<array<string, mixed>> $components */
                self::walk($components, $stapUuid, $scenarios);
            }
        }

        return $scenarios;
    }

    /**
     * @param  list<array<string, mixed>>  $components
     */
    private static function indexFieldTypes(array $components): void
    {
        foreach ($components as $c) {
            if (isset($c['key'], $c['type']) && is_string($c['key']) && is_string($c['type'])) {
                self::$fieldTypeCache[$c['key']] = $c['type'];
            }
            if (is_array($c['components'] ?? null)) {
                /** @var list<array<string, mixed>> $nested */
                $nested = $c['components'];
                self::indexFieldTypes($nested);
            }
            if (($c['type'] ?? null) === 'columns' && is_array($c['columns'] ?? null)) {
                foreach ($c['columns'] as $col) {
                    if (is_array($col) && is_array($col['components'] ?? null)) {
                        /** @var list<array<string, mixed>> $nested */
                        $nested = $col['components'];
                        self::indexFieldTypes($nested);
                    }
                }
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $components
     * @param  array<string, array<int, array<string, mixed>>>  $scenarios
     */
    private static function walk(array $components, string $stapUuid, array &$scenarios): void
    {
        foreach ($components as $component) {
            self::visit($component, $stapUuid, $scenarios);
        }
    }

    /**
     * @param  array<string, mixed>  $component
     * @param  array<string, array<int, array<string, mixed>>>  $scenarios
     */
    private static function visit(array $component, string $stapUuid, array &$scenarios): void
    {
        $key = (string) ($component['key'] ?? '');
        $conditional = $component['conditional'] ?? null;

        if ($key !== '' && is_array($conditional) && ! empty($conditional['when'])) {
            // Content-componenten zijn display-only zonder key-marker in
            // de rendered HTML — hun zichtbaarheid is moeilijk betrouwbaar
            // te detecteren via auto-gen. Die laten we handmatig testen
            // als het nodig is.
            if (($component['type'] ?? null) === 'content') {
                return;
            }
            foreach (self::scenariosVoorConditional($key, $stapUuid, $conditional) as $label => $entry) {
                $scenarios[$label] = $entry;
            }
        }

        if (is_array($component['components'] ?? null)) {
            /** @var list<array<string, mixed>> $nested */
            $nested = $component['components'];
            self::walk($nested, $stapUuid, $scenarios);
        }
        if (($component['type'] ?? null) === 'columns' && is_array($component['columns'] ?? null)) {
            foreach ($component['columns'] as $column) {
                if (is_array($column) && is_array($column['components'] ?? null)) {
                    /** @var list<array<string, mixed>> $nested */
                    $nested = $column['components'];
                    self::walk($nested, $stapUuid, $scenarios);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $conditional
     * @return array<string, array<int, array<string, mixed>>>
     */
    private static function scenariosVoorConditional(string $target, string $stapUuid, array $conditional): array
    {
        $triggerVeld = (string) $conditional['when'];
        $matchWaarde = $conditional['eq'] ?? null;
        $show = (bool) ($conditional['show'] ?? true);

        $zichtbaarBijMatch = $show;
        $zichtbaarBijGeenMatch = ! $show;

        $triggerType = self::$fieldTypeCache[$triggerVeld] ?? 'textfield';

        // Genereer data-waarde in het formaat dat het trigger-component verwacht:
        //  - selectboxes → map {eq: true}
        //  - anders → plain waarde
        $gegevenMatch = self::dataShapeVoorMatch($triggerVeld, $triggerType, $matchWaarde);
        $gegevenGeenMatch = self::dataShapeVoorGeenMatch($triggerVeld, $triggerType, $matchWaarde);

        $matchWaardeWeergave = self::weergaveWaarde($matchWaarde);
        $situatie = $show
            ? "Veld zichtbaar wanneer {$triggerVeld} = {$matchWaardeWeergave}"
            : "Veld verborgen wanneer {$triggerVeld} = {$matchWaardeWeergave}";

        return [
            "[auto] {$target}: {$situatie} (match)" => [[
                'naam' => "Zichtbaarheid \"{$target}\" — trigger matcht (auto)",
                'omschrijving' =>
                    "Zodra de gebruiker een waarde kiest die matcht met de conditional — ".
                    "`{$triggerVeld}` = `".self::printable($matchWaarde).'` — moet veld '.
                    "`{$target}` ".($zichtbaarBijMatch ? '**zichtbaar** worden' : '**verborgen** worden').
                    '. Dit scenario test de match-kant van de conditional.',
                'categorie' => 'visibility',
                'stap' => $stapUuid,
                'trigger_velden' => [$triggerVeld],
                'gegeven' => $gegevenMatch,
                'verwacht' => [
                    'field_visible.'.$target => $zichtbaarBijMatch,
                ],
            ]],

            "[auto] {$target}: {$situatie} (no-match)" => [[
                'naam' => "Zichtbaarheid \"{$target}\" — trigger matcht niet (auto)",
                'omschrijving' =>
                    "Met een waarde die niet matcht — `{$triggerVeld}` is iets anders dan ".
                    "`".self::printable($matchWaarde).'` — moet veld '.
                    "`{$target}` ".($zichtbaarBijGeenMatch ? '**zichtbaar** zijn' : '**verborgen** zijn').
                    '. Dit scenario test de andere kant van de conditional.',
                'categorie' => 'visibility',
                'stap' => $stapUuid,
                'trigger_velden' => [$triggerVeld],
                'gegeven' => $gegevenGeenMatch,
                'verwacht' => [
                    'field_visible.'.$target => $zichtbaarBijGeenMatch,
                ],
            ]],
        ];
    }

    /**
     * Voor de match-kant: bouw een gegeven-array die het trigger-veld
     * zó vult dat de conditional.when-expressie `waar` wordt.
     *
     * @return array<string, mixed>
     */
    private static function dataShapeVoorMatch(string $veld, string $type, mixed $eq): array
    {
        if ($type === 'selectboxes') {
            // Filament's CheckboxList houdt de geselecteerde opties als
            // lijst van strings — `['vooraf']`. De emit-te conditional-
            // closure checkt via `in_array($eq, (array) $get($veld), true)`.
            return [$veld => is_string($eq) ? [$eq] : [(string) $eq]];
        }
        if ($eq === '') {
            // Match voor "leeg-veld"-conditional: veld expliciet op null
            // (Filament's default voor ongevulde inputs).
            return [$veld => null];
        }

        return [$veld => $eq];
    }

    /**
     * Voor de no-match kant: zorg dat de trigger-expressie NIET matcht.
     *
     * @return array<string, mixed>
     */
    private static function dataShapeVoorGeenMatch(string $veld, string $type, mixed $eq): array
    {
        if ($type === 'selectboxes') {
            // Lege lijst = geen enkele optie aangevinkt → match faalt.
            return [$veld => []];
        }
        if ($eq === '') {
            // No-match voor "leeg-veld"-conditional: veld wél gevuld zetten.
            return [$veld => 'niet leeg'];
        }

        return [$veld => self::NO_MATCH_MARKER];
    }

    private static function weergaveWaarde(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_string($value)) {
            return $value === '' ? "'' (leeg)" : "'{$value}'";
        }

        return (string) json_encode($value);
    }

    private static function printable(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return (string) json_encode($value);
    }
}
