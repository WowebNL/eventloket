<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\EventForm\Reporting\FieldCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\Feature\EventForm\Equivalence\EquivalenceScenario;
use Tests\Feature\EventForm\Equivalence\Scenarios\ScenarioProvider;

/**
 * Genereert de gedragsspecificatie — een leesbare samenvatting van wat
 * het evenementformulier doet, gegroepeerd per pagina. Output bestaat
 * uit één index + één bestand per pagina, gelinked vanuit de index.
 *
 * Structuur:
 *   docs/gedragsspecificatie.md                   (index + ✅/❌ overzicht)
 *   docs/gedragsspecificatie/stap-01-contactgegevens.md
 *   docs/gedragsspecificatie/stap-02-het-evenement.md
 *   …
 *   docs/gedragsspecificatie/pagina-overstijgend.md
 */
class EventFormGedragsRapport extends Command
{
    protected $signature = 'eventform:gedrags-rapport
        {--out=docs/gedragsspecificatie.md : Pad naar het index-bestand. Subbestanden komen in een gelijk-genaamde directory naast de index.}';

    protected $description = 'Genereert de gedragsspecificatie (1 index + 1 bestand per pagina)';

    private FieldCatalog $catalog;

    public function handle(): int
    {
        $this->catalog = FieldCatalog::fromLocalDump();

        $providers = $this->discoverProviders();
        if ($providers === []) {
            $this->error('Geen ScenarioProvider-klassen gevonden in tests/Feature/EventForm/Equivalence/Scenarios/');

            return self::FAILURE;
        }

        $results = $this->runAllScenarios($providers);

        // Bepaal paths: indexPath = --out, pagesDir = zelfde dirname + basename-zonder-extensie
        $indexPath = base_path((string) $this->option('out'));
        $pagesDir = dirname($indexPath).'/'.pathinfo($indexPath, PATHINFO_FILENAME);

        // Schoon pages-directory voordat we schrijven — stale files van een oudere
        // run horen weg te zijn (bv. als een stap verdwijnt uit het formulier).
        if (is_dir($pagesDir)) {
            foreach (glob($pagesDir.'/*.md') ?: [] as $stale) {
                @unlink($stale);
            }
        }
        @mkdir($pagesDir, 0755, true);
        @mkdir(dirname($indexPath), 0755, true);

        $grouped = $this->groupByStap($results);

        // Eerst per-pagina files schrijven zodat we filename-links kunnen gebruiken in de index
        $pageFiles = $this->writePageFiles($grouped, $pagesDir);

        // Nu de index met verwijzingen naar de zojuist-geschreven files
        file_put_contents($indexPath, $this->renderIndex($results, $pageFiles, $pagesDir, $indexPath));

        $this->info("Index: {$indexPath}");
        $this->info('Pagina-bestanden: '.count($pageFiles).' × in '.$pagesDir);

        return self::SUCCESS;
    }

    /**
     * @return list<class-string<ScenarioProvider>>
     */
    private function discoverProviders(): array
    {
        $dir = base_path('tests/Feature/EventForm/Equivalence/Scenarios');
        if (! is_dir($dir)) {
            return [];
        }
        $providers = [];
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
            $providers[] = $fqcn;
        }

        return $providers;
    }

    /**
     * @param  list<class-string<ScenarioProvider>>  $providers
     * @return list<array{provider: class-string<ScenarioProvider>, scenario: array<string, mixed>, pass: bool, diffs: array<string, mixed>}>
     */
    private function runAllScenarios(array $providers): array
    {
        $results = [];
        foreach ($providers as $provider) {
            foreach ($provider::all() as $entry) {
                $scenario = $entry[0];
                $diffs = EquivalenceScenario::run($scenario);
                $results[] = [
                    'provider' => $provider,
                    'scenario' => $scenario,
                    'pass' => $diffs === [],
                    'diffs' => $diffs,
                ];
            }
        }

        return $results;
    }

    /**
     * Groepeer op stap-uuid, in OF-index-volgorde. Cross-cutting (stap=null)
     * komt als laatste entry.
     *
     * @param  list<array{provider: class-string<ScenarioProvider>, scenario: array<string, mixed>, pass: bool, diffs: array<string, mixed>}>  $results
     * @return array<string, list<array{provider: class-string<ScenarioProvider>, scenario: array<string, mixed>, pass: bool, diffs: array<string, mixed>}>>
     */
    private function groupByStap(array $results): array
    {
        $grouped = [];
        foreach ($results as $result) {
            $key = $result['scenario']['stap'] ?? '__cross_cutting__';
            $grouped[$key][] = $result;
        }

        $ordered = [];
        foreach ($this->catalog->allSteps() as $uuid => $meta) {
            if (isset($grouped[$uuid])) {
                $ordered[$uuid] = $grouped[$uuid];
                unset($grouped[$uuid]);
            }
        }
        foreach ($grouped as $key => $items) {
            if ($key !== '__cross_cutting__') {
                $ordered[$key] = $items;
            }
        }
        if (isset($grouped['__cross_cutting__'])) {
            $ordered['__cross_cutting__'] = $grouped['__cross_cutting__'];
        }

        return $ordered;
    }

    /**
     * @param  array<string, list<array{provider: class-string<ScenarioProvider>, scenario: array<string, mixed>, pass: bool, diffs: array<string, mixed>}>>  $grouped
     * @return array<string, string>  stap-key → absolute path naar geschreven file
     */
    private function writePageFiles(array $grouped, string $pagesDir): array
    {
        $paths = [];
        foreach ($grouped as $stapKey => $items) {
            $slug = $this->slugForStap($stapKey);
            $path = $pagesDir.'/'.$slug.'.md';
            file_put_contents($path, $this->renderPage($stapKey, $items));
            $paths[$stapKey] = $path;
        }

        return $paths;
    }

    private function slugForStap(string $stapKey): string
    {
        if ($stapKey === '__cross_cutting__') {
            return 'pagina-overstijgend';
        }
        $index = $this->catalog->stepIndex($stapKey);
        $label = $this->catalog->stepLabel($stapKey);
        if ($index === null || $label === null) {
            return 'stap-'.substr($stapKey, 0, 8);
        }
        // "Stap 1: Contactgegevens" → "contactgegevens"
        $name = preg_replace('/^Stap \d+: /', '', $label) ?? $label;
        $slug = Str::slug($name);
        $paddedIndex = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);

        return "stap-{$paddedIndex}-{$slug}";
    }

    /**
     * @param  list<array{provider: class-string<ScenarioProvider>, scenario: array<string, mixed>, pass: bool, diffs: array<string, mixed>}>  $items
     */
    private function renderPage(string $stapKey, array $items): string
    {
        $titel = $stapKey === '__cross_cutting__'
            ? 'Pagina-overstijgend gedrag'
            : ($this->catalog->stepLabel($stapKey) ?? "Stap {$stapKey}");

        $pass = count(array_filter($items, static fn ($r) => $r['pass']));
        $fail = count($items) - $pass;
        $status = $fail === 0 ? '✅ Alle scenarios op deze pagina slagen' : "❌ {$fail} van ".count($items).' scenarios faalt';

        $lines = [
            "# {$titel}",
            '',
            "_[← terug naar de index](../gedragsspecificatie.md)_",
            '',
            "**Samenvatting:** {$status} — {$pass}/".count($items).' gedekt.',
            '',
        ];

        if ($stapKey === '__cross_cutting__') {
            $lines[] = 'Dit bestand verzamelt gedrag dat niet aan één specifieke pagina gekoppeld is: '
                .'routering naar registratie-backends, afgeleide berekeningen die van meerdere '
                .'pagina\'s tegelijk afhangen, en service-uitwisseling met externe systemen.';
            $lines[] = '';
        }

        // Toon inleiding per scenario-provider (1× per provider, ook als er meerdere providers bijdragen aan deze pagina)
        $seenProviders = [];
        foreach ($items as $item) {
            $p = $item['provider'];
            if (! isset($seenProviders[$p])) {
                $seenProviders[$p] = true;
                $lines[] = '## '.$p::kop();
                $lines[] = '';
                $lines[] = $p::inleiding();
                $lines[] = '';
            }
        }

        // Elk scenario
        foreach ($items as $item) {
            $lines[] = $this->renderScenario($item['scenario'], $item['pass'], $item['diffs']);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<array{provider: class-string<ScenarioProvider>, scenario: array<string, mixed>, pass: bool, diffs: array<string, mixed>}>  $results
     * @param  array<string, string>  $pageFiles  stap-key → absolute path
     */
    private function renderIndex(array $results, array $pageFiles, string $pagesDir, string $indexPath): string
    {
        $total = count($results);
        $passed = count(array_filter($results, static fn ($r) => $r['pass']));
        $failed = $total - $passed;

        $lines = [
            '# Gedragsspecificatie evenementformulier',
            '',
            '_Automatisch gegenereerd op '.now()->format('d-m-Y H:i').' via `php artisan eventform:gedrags-rapport`._',
            '',
            '**Samenvatting:** '.($failed === 0 ? '✅ Alle scenarios slagen' : "❌ {$failed} van {$total} scenarios faalt")
                ." — {$passed} geslaagd, {$failed} gefaald, {$total} totaal.",
            '',
            'Dit document is de index op de gedragsspecificatie. Elke pagina van het '
                .'evenementformulier heeft een eigen bestand waarin de scenarios voor dat '
                .'gedeelte beschreven staan. ✅ betekent: de Filament-versie reageert exact '
                .'zoals Open Forms zou doen. ❌ betekent: er is een afwijking die '
                .'onderzocht moet worden.',
            '',
            '---',
            '',
            '## Overzicht per pagina',
            '',
        ];

        // Counts per stap-key
        $counts = [];
        foreach ($results as $result) {
            $key = $result['scenario']['stap'] ?? '__cross_cutting__';
            $counts[$key] ??= ['pass' => 0, 'fail' => 0];
            $counts[$key][$result['pass'] ? 'pass' : 'fail']++;
        }

        $pagesDirRelative = ltrim(str_replace(dirname($indexPath), '', $pagesDir), '/');

        foreach ($this->catalog->allSteps() as $uuid => $meta) {
            if (! isset($counts[$uuid])) {
                $titel = $this->catalog->stepLabel($uuid);
                if ($this->catalog->stepHasLogic($uuid)) {
                    $lines[] = "- _⚪ {$titel}_ — nog geen scenarios gedekt";
                } else {
                    $lines[] = "- 🟢 _{$titel}_ — geen dynamisch gedrag (pure input-/inhoudspagina, niks te testen)";
                }

                continue;
            }
            $lines[] = $this->renderIndexRow($uuid, $counts[$uuid], $pageFiles[$uuid] ?? null, $pagesDirRelative);
            unset($counts[$uuid]);
        }
        foreach ($counts as $key => $c) {
            if ($key === '__cross_cutting__') {
                continue;
            }
            // Onbekende stap-uuid (bv. stap inmiddels weg uit OF); val terug op raw
            $lines[] = $this->renderIndexRow($key, $c, $pageFiles[$key] ?? null, $pagesDirRelative);
            unset($counts[$key]);
        }
        if (isset($counts['__cross_cutting__'])) {
            $lines[] = '';
            $lines[] = '## Pagina-overstijgend gedrag';
            $lines[] = '';
            $lines[] = $this->renderIndexRow('__cross_cutting__', $counts['__cross_cutting__'], $pageFiles['__cross_cutting__'] ?? null, $pagesDirRelative);
        }

        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';
        $lines[] = 'Nieuwe scenarios toevoegen kan door een class toe te voegen in `tests/Feature/EventForm/Equivalence/Scenarios/` die `ScenarioProvider` implementeert. Bij de volgende run van `eventform:gedrags-rapport` verschijnt hij automatisch in het juiste paginabestand.';

        return implode("\n", $lines);
    }

    /**
     * @param  array{pass: int, fail: int}  $c
     */
    private function renderIndexRow(string $stapKey, array $c, ?string $pageFile, string $pagesDirRelative): string
    {
        $total = $c['pass'] + $c['fail'];
        $status = $c['fail'] === 0 ? '✅' : '❌';
        $scenarioWoord = $total === 1 ? 'scenario' : 'scenarios';
        $countText = "{$c['pass']}/{$total} {$scenarioWoord}".($c['fail'] > 0 ? " ({$c['fail']} gefaald)" : '');

        $titel = $stapKey === '__cross_cutting__'
            ? 'Pagina-overstijgend gedrag'
            : ($this->catalog->stepLabel($stapKey) ?? "Stap {$stapKey}");

        if ($pageFile !== null) {
            $filename = basename($pageFile);
            $link = $pagesDirRelative.'/'.$filename;

            return "- {$status} **[{$titel}]({$link})** — {$countText}";
        }

        return "- {$status} **{$titel}** — {$countText}";
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @param  array<string, mixed>  $diffs
     */
    private function renderScenario(array $scenario, bool $pass, array $diffs): string
    {
        $icon = $pass ? '✅' : '❌';
        $lines = [];
        $lines[] = "### {$icon} {$scenario['naam']}";
        $lines[] = '';
        $lines[] = $scenario['omschrijving'];
        $lines[] = '';

        if (! empty($scenario['gegeven'])) {
            $lines[] = '**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**';
            foreach ($scenario['gegeven'] as $key => $value) {
                $lines[] = '- '.$this->formatGegeven($key, $value);
            }
            $lines[] = '';
        }

        if (! empty($scenario['verwacht'])) {
            $lines[] = '**Dan verwachten we:**';
            foreach ($scenario['verwacht'] as $key => $value) {
                $lines[] = '- '.$this->formatVerwachting($key, $value);
            }
            $lines[] = '';
        }

        if (! $pass) {
            $lines[] = '> **Afwijking:**';
            foreach ($diffs as $path => $diff) {
                $lines[] = '> - `'.$path.'` — verwacht: `'.json_encode($diff['expected']).'`, werkelijk: `'.json_encode($diff['actual']).'`';
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function formatGegeven(string $key, mixed $value): string
    {
        $label = $this->catalog->fieldLabel($key);

        if (is_array($value)) {
            $checks = [];
            foreach ($value as $optValue => $state) {
                $optLabel = $this->catalog->optionLabel($key, (string) $optValue);
                $omschrijving = $optLabel !== null ? "\"{$optLabel}\"" : "`{$optValue}`";
                $checks[] = $state === true ? "{$omschrijving} aangevinkt" : "{$omschrijving} uit";
            }
            $veldOmschrijving = $label !== null ? "Veld \"{$label}\"" : "Veld `{$key}`";

            return $veldOmschrijving.' — '.implode(', ', $checks);
        }

        $optLabel = is_string($value) ? $this->catalog->optionLabel($key, $value) : null;
        $veldOmschrijving = $label !== null ? "Veld \"{$label}\"" : "Veld `{$key}`";
        $waarde = $this->formatWaarde($value);
        if ($optLabel !== null) {
            $waarde .= " (_{$optLabel}_)";
        }

        return "{$veldOmschrijving} = {$waarde}";
    }

    private function formatVerwachting(string $path, mixed $value): string
    {
        if (Str::startsWith($path, 'field_hidden.')) {
            $field = Str::after($path, 'field_hidden.');
            $label = $this->catalog->fieldLabel($field);
            $stap = $this->catalog->fieldStep($field);
            $stapHint = $stap !== null && $this->catalog->stepLabel($stap) !== null ? ' _(op '.$this->catalog->stepLabel($stap).')_' : '';
            $veldOmschrijving = $label !== null ? "Veld \"{$label}\"" : "Veld `{$field}`";
            $action = $value === false ? '**zichtbaar**' : '**verborgen**';

            return "{$veldOmschrijving}{$stapHint} wordt {$action}";
        }
        if (Str::startsWith($path, 'step_applicable.')) {
            $stepUuid = Str::after($path, 'step_applicable.');
            $stapLabel = $this->catalog->stepLabel($stepUuid) ?? "Stap {$stepUuid}";
            $action = $value === true ? '**van toepassing** (getoond in sidebar)' : '**niet van toepassing** (doorgestreept in sidebar)';

            return "{$stapLabel} wordt {$action}";
        }
        if (Str::startsWith($path, 'system.')) {
            $sysKey = Str::after($path, 'system.');

            return "Systeem-waarde `{$sysKey}` = {$this->formatWaarde($value)}";
        }

        $label = $this->catalog->fieldLabel($path);
        $veldOmschrijving = $label !== null ? "Veld \"{$label}\"" : "Veld `{$path}`";

        return "{$veldOmschrijving} = {$this->formatWaarde($value)}";
    }

    private function formatWaarde(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '**ja**' : '**nee**';
        }
        if (is_string($value)) {
            return "\"{$value}\"";
        }

        return '`'.json_encode($value).'`';
    }
}
