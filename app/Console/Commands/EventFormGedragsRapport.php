<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\Feature\EventForm\Equivalence\EquivalenceScenario;
use Tests\Feature\EventForm\Equivalence\Scenarios\ScenarioProvider;

/**
 * Genereert `docs/gedragsspecificatie.md` — een leesbare samenvatting van
 * wat het evenementformulier doet in termen die niet-devs ook begrijpen,
 * met naast elk scenario een ✅/❌ om te laten zien dat het gedrag werkt
 * zoals beschreven. Dient als oplever-document: iedereen kan op GitHub de
 * markdown openen en zien dat de nieuwe Filament-versie zich gedraagt als
 * de oude Open Forms-versie.
 */
class EventFormGedragsRapport extends Command
{
    protected $signature = 'eventform:gedrags-rapport
        {--out=docs/gedragsspecificatie.md : Bestand waar de rapportage naartoe gaat}
        {--stdout : Print naar stdout in plaats van naar bestand}';

    protected $description = 'Genereert een leesbaar rapport van alle equivalentie-scenarios met ✅/❌ per geval';

    public function handle(): int
    {
        $providers = $this->discoverProviders();
        if ($providers === []) {
            $this->error('Geen ScenarioProvider-klassen gevonden in tests/Feature/EventForm/Equivalence/Scenarios/');

            return self::FAILURE;
        }

        $report = $this->renderReport($providers);

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

        // Stabiele sortering: routing eerst, dan visibility, computation, services — dan alfabetisch.
        $categoryOrder = ['routing' => 0, 'visibility' => 1, 'computation' => 2, 'services' => 3];
        usort($providers, function (string $a, string $b) use ($categoryOrder): int {
            $ca = $categoryOrder[$a::categorie()] ?? 99;
            $cb = $categoryOrder[$b::categorie()] ?? 99;

            return $ca <=> $cb ?: $a::kop() <=> $b::kop();
        });

        return $providers;
    }

    /** @param  list<class-string<ScenarioProvider>>  $providers */
    private function renderReport(array $providers): string
    {
        $generatedAt = now()->format('d-m-Y H:i');
        $totalPass = 0;
        $totalFail = 0;

        $bodyParts = [];
        foreach ($providers as $provider) {
            $bodyParts[] = $this->renderProvider($provider, $totalPass, $totalFail);
        }

        $total = $totalPass + $totalFail;
        $overall = $totalFail === 0 ? '✅ Alle scenarios slagen' : "❌ {$totalFail} van {$total} scenarios faalt";

        $header = "# Gedragsspecificatie evenementformulier\n\n"
            ."_Automatisch gegenereerd op {$generatedAt} via `php artisan eventform:gedrags-rapport`._\n\n"
            ."**Samenvatting: {$overall}** — {$totalPass} geslaagd, {$totalFail} gefaald, {$total} totaal.\n\n"
            ."Dit document beschrijft in mensentaal hoe het evenementformulier zich gedraagt. "
            ."Elke beschrijving is gekoppeld aan een geautomatiseerde test die het gedrag "
            ."bewijst — ✅ betekent: de Filament-versie reageert exact zoals Open Forms zou "
            ."doen onder dezelfde omstandigheden. ❌ betekent: er is een afwijking die "
            ."onderzocht moet worden.\n\n"
            ."---\n\n";

        return $header.implode("\n---\n\n", $bodyParts);
    }

    /** @param  class-string<ScenarioProvider>  $provider */
    private function renderProvider(string $provider, int &$totalPass, int &$totalFail): string
    {
        $scenarios = $provider::all();
        $lines = [];
        $lines[] = "## {$provider::kop()}";
        $lines[] = '';
        $lines[] = $provider::inleiding();
        $lines[] = '';

        $passCount = 0;
        $failCount = 0;
        $failedScenarios = [];

        $rendered = [];
        foreach ($scenarios as $entry) {
            $scenario = $entry[0];
            $diffs = EquivalenceScenario::run($scenario);
            $passed = $diffs === [];
            if ($passed) {
                $passCount++;
                $totalPass++;
            } else {
                $failCount++;
                $totalFail++;
                $failedScenarios[] = ['scenario' => $scenario, 'diffs' => $diffs];
            }
            $rendered[] = $this->renderScenario($scenario, $passed, $diffs);
        }

        $totalScenarios = $passCount + $failCount;
        $statusBadge = $failCount === 0
            ? "✅ {$passCount}/{$passCount} scenarios slagen"
            : "❌ {$failCount} van {$totalScenarios} scenarios faalt";
        $lines[] = "**{$statusBadge}**";
        $lines[] = '';
        $lines = array_merge($lines, $rendered);

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @param  array<string, array{expected: mixed, actual: mixed}>  $diffs
     */
    private function renderScenario(array $scenario, bool $passed, array $diffs): string
    {
        $status = $passed ? '✅' : '❌';
        $lines = [];
        $lines[] = "### {$status} {$scenario['naam']}";
        $lines[] = '';
        $lines[] = $scenario['omschrijving'];
        $lines[] = '';

        // "Gegeven" — wat de gebruiker heeft ingevuld
        if (! empty($scenario['gegeven'])) {
            $lines[] = '**Gegeven:**';
            foreach ($scenario['gegeven'] as $key => $value) {
                $lines[] = '- '.$this->formatGegeven($key, $value);
            }
            $lines[] = '';
        }

        // "Dan verwachten we" — wat eruit moet komen
        if (! empty($scenario['verwacht'])) {
            $lines[] = '**Dan verwachten we:**';
            foreach ($scenario['verwacht'] as $key => $value) {
                $lines[] = '- '.$this->formatVerwachting($key, $value);
            }
            $lines[] = '';
        }

        if (! $passed) {
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
        if (is_array($value)) {
            // Typisch een selectboxes-achtige map: {A3: true, A1: false}
            $checks = [];
            foreach ($value as $k => $v) {
                $checks[] = $v === true ? "`{$k}` aangevinkt" : "`{$k}`={$this->short($v)}";
            }

            return "`{$key}` = [".implode(', ', $checks).']';
        }

        return "`{$key}` = {$this->short($value)}";
    }

    private function formatVerwachting(string $path, mixed $value): string
    {
        if (Str::startsWith($path, 'field_hidden.')) {
            $field = Str::after($path, 'field_hidden.');
            $action = $value === false ? 'zichtbaar' : 'verborgen';

            return "veld `{$field}` is **{$action}**";
        }
        if (Str::startsWith($path, 'step_applicable.')) {
            $stepUuid = Str::after($path, 'step_applicable.');
            $action = $value === true ? 'van toepassing' : 'niet van toepassing';

            return "stap `{$stepUuid}` is **{$action}**";
        }
        if (Str::startsWith($path, 'system.')) {
            $sysKey = Str::after($path, 'system.');

            return "system-waarde `{$sysKey}` = {$this->short($value)}";
        }

        return "`{$path}` = {$this->short($value)}";
    }

    private function short(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '**true**' : '**false**';
        }
        if (is_string($value)) {
            return "`'{$value}'`";
        }

        return '`'.json_encode($value).'`';
    }
}
