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
 * Genereert `docs/gedragsspecificatie.md` — een leesbare samenvatting van
 * wat het evenementformulier doet, gegroepeerd per pagina/stap waar het
 * gedrag merkbaar is. Bedoeld als oplever-document: iedereen kan op
 * GitHub zien dat de nieuwe Filament-versie zich gedraagt zoals de oude
 * Open Forms-versie.
 */
class EventFormGedragsRapport extends Command
{
    protected $signature = 'eventform:gedrags-rapport
        {--out=docs/gedragsspecificatie.md : Bestand waar de rapportage naartoe gaat}
        {--stdout : Print naar stdout in plaats van naar bestand}';

    protected $description = 'Genereert een leesbaar rapport van alle equivalentie-scenarios, gegroepeerd per stap';

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
        $report = $this->renderReport($results);

        if ($this->option('stdout')) {
            $this->output->write($report);

            return self::SUCCESS;
        }

        $out = base_path((string) $this->option('out'));
        @mkdir(dirname($out), 0755, true);
        file_put_contents($out, $report);
        $this->info("Rapport geschreven naar: {$out}");

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
     * Run alle scenarios één keer en bewaar de uitkomsten. We groeperen
     * pas in de render-stap, want de bronsplitsing (routing/visibility)
     * en de gewenste groepering (per stap) komen niet altijd overeen.
     *
     * @param  list<class-string<ScenarioProvider>>  $providers
     * @return list<array{provider: class-string<ScenarioProvider>, scenario: array<string, mixed>, pass: bool, diffs: array<string, array{expected: mixed, actual: mixed}>}>
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
     * @param  list<array{provider: class-string<ScenarioProvider>, scenario: array<string, mixed>, pass: bool, diffs: array<string, mixed>}>  $results
     */
    private function renderReport(array $results): string
    {
        $total = count($results);
        $passed = count(array_filter($results, static fn ($r) => $r['pass']));
        $failed = $total - $passed;

        $header = "# Gedragsspecificatie evenementformulier\n\n"
            .'_Automatisch gegenereerd op '.now()->format('d-m-Y H:i').' via `php artisan eventform:gedrags-rapport`._'."\n\n"
            .'**Samenvatting:** '.($failed === 0 ? '✅ Alle scenarios slagen' : "❌ {$failed} van {$total} scenarios faalt")
            ." — {$passed} geslaagd, {$failed} gefaald, {$total} totaal.\n\n"
            ."Dit document beschrijft in mensentaal hoe het evenementformulier zich gedraagt, "
            ."gegroepeerd per pagina. Elke beschrijving is gekoppeld aan een geautomatiseerde "
            ."test die het gedrag bewijst — ✅ betekent: de Filament-versie reageert exact "
            ."zoals Open Forms zou doen onder dezelfde omstandigheden. ❌ betekent: er is een "
            ."afwijking die onderzocht moet worden.\n\n"
            ."---\n\n"
            .$this->renderInhoudsopgave($results)
            ."\n---\n\n";

        // Groepeer op stap-uuid. null = pagina-overstijgend.
        $grouped = [];
        foreach ($results as $result) {
            $stapUuid = $result['scenario']['stap'] ?? null;
            $key = $stapUuid ?? '__cross_cutting__';
            $grouped[$key][] = $result;
        }

        // Rendervolgorde: eerst stappen op OF-index, dan cross-cutting
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

        $sections = [];
        foreach ($ordered as $stapKey => $items) {
            $sections[] = $this->renderStapSectie($stapKey, $items);
        }

        return $header.implode("\n---\n\n", $sections);
    }

    /**
     * @param  list<array{provider: class-string<ScenarioProvider>, scenario: array<string, mixed>, pass: bool, diffs: array<string, mixed>}>  $results
     */
    private function renderInhoudsopgave(array $results): string
    {
        $counts = [];
        foreach ($results as $result) {
            $stapUuid = $result['scenario']['stap'] ?? null;
            $key = $stapUuid ?? '__cross_cutting__';
            $counts[$key] ??= ['pass' => 0, 'fail' => 0];
            $counts[$key][$result['pass'] ? 'pass' : 'fail']++;
        }

        $lines = ["## Inhoudsopgave\n"];
        foreach ($this->catalog->allSteps() as $uuid => $meta) {
            if (! isset($counts[$uuid])) {
                continue;
            }
            $c = $counts[$uuid];
            $status = $c['fail'] === 0 ? '✅' : '❌';
            $label = $this->catalog->stepLabel($uuid);
            $scenarioWoord = $c['pass'] + $c['fail'] === 1 ? 'scenario' : 'scenarios';
            $lines[] = "- {$status} [{$label}](#".$this->anchor($label).") — {$c['pass']} {$scenarioWoord}".($c['fail'] > 0 ? " ({$c['fail']} gefaald)" : '');
            unset($counts[$uuid]);
        }
        // Onbekende stappen (mogelijk gewist in OF): val terug op uuid
        foreach ($counts as $key => $c) {
            if ($key === '__cross_cutting__') {
                continue;
            }
            $status = $c['fail'] === 0 ? '✅' : '❌';
            $scenarioWoord = $c['pass'] + $c['fail'] === 1 ? 'scenario' : 'scenarios';
            $lines[] = "- {$status} Stap {$key} — {$c['pass']} {$scenarioWoord}".($c['fail'] > 0 ? " ({$c['fail']} gefaald)" : '');
            unset($counts[$key]);
        }
        if (isset($counts['__cross_cutting__'])) {
            $c = $counts['__cross_cutting__'];
            $status = $c['fail'] === 0 ? '✅' : '❌';
            $scenarioWoord = $c['pass'] + $c['fail'] === 1 ? 'scenario' : 'scenarios';
            $lines[] = "- {$status} [Pagina-overstijgend gedrag](#pagina-overstijgend-gedrag) — {$c['pass']} {$scenarioWoord}".($c['fail'] > 0 ? " ({$c['fail']} gefaald)" : '');
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  list<array{provider: class-string<ScenarioProvider>, scenario: array<string, mixed>, pass: bool, diffs: array<string, mixed>}>  $items
     */
    private function renderStapSectie(string $stapKey, array $items): string
    {
        $titel = $stapKey === '__cross_cutting__'
            ? 'Pagina-overstijgend gedrag'
            : ($this->catalog->stepLabel($stapKey) ?? "Stap {$stapKey}");

        $lines = ["## {$titel}", ''];

        if ($stapKey === '__cross_cutting__') {
            $lines[] = 'Gedrag dat niet aan één specifieke pagina gekoppeld is — routering, '
                .'afgeleide berekeningen, service-uitwisseling met externe systemen.';
            $lines[] = '';
        }

        // Introductie uit de scenario-provider (alleen bij de eerste scenario-provider gebruikt)
        $seenProviders = [];
        foreach ($items as $item) {
            $p = $item['provider'];
            if (! isset($seenProviders[$p])) {
                $seenProviders[$p] = true;
                $lines[] = '_'.$p::kop().'_ — '.$p::inleiding();
                $lines[] = '';
            }
        }

        foreach ($items as $item) {
            $lines[] = $this->renderScenario($item['scenario'], $item['pass'], $item['diffs']);
        }

        return implode("\n", $lines);
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

        // Multi-select: {A3: true, A1: false} → opsomming met optie-labels
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

        // Scalar — toon label + waarde (+ eventueel optie-label voor selectjes)
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

    private function anchor(string $titel): string
    {
        return (string) preg_replace('/[^a-z0-9-]+/', '-', strtolower(trim($titel)));
    }
}
