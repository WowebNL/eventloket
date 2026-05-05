<?php

declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel;

/**
 * Inventaris-script: scant alle 144 transpiled rules + de 2 handgeschreven
 * en produceert een markdown-rapport met per rule:
 *
 *   - class-naam (huidige cryptische naam)
 *   - uuid
 *   - description (uit @openforms-rule-description)
 *   - applies()-expressie als raw PHP
 *   - lijst van actions, gecategoriseerd:
 *       * setVariable('foo', <expr>)
 *       * setFieldHidden('field', bool)
 *       * setStepApplicable('uuid', bool)
 *       * fetch('service')
 *
 * Bedoeld als werkbasis voor de rules-engine-refactor: ik kan hieruit
 * lezen welke variabele door welke rule(s) bepaald wordt, welke velden
 * door welke condities verborgen worden, etc. Plus: het rapport is
 * permanente trace-documentatie die naar Markdown gaat zodat we ook na
 * verwijderen van de rule-files kunnen zien wie wat deed.
 *
 * Run: ./vendor/bin/sail exec laravel.test php dev-scripts/rules-inventory.php > docs/rules-inventory.md
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$dir = __DIR__.'/../app/EventForm/Rules';
$files = glob("$dir/*.php");

if ($files === false) {
    fwrite(STDERR, "Geen rule-files gevonden in $dir\n");
    exit(1);
}

// Filter de niet-rule-files (Rule.php interface, RulesEngine.php, RuleRegistry.php).
$skip = ['Rule.php', 'RulesEngine.php', 'RuleRegistry.php'];
$ruleFiles = [];
foreach ($files as $f) {
    if (! in_array(basename($f), $skip, true)) {
        $ruleFiles[] = $f;
    }
}
sort($ruleFiles);

echo "# Rules-inventaris\n\n";
echo 'Gegenereerd op '.date('Y-m-d H:i')."\n";
echo 'Totaal rules: '.count($ruleFiles)."\n\n";
echo "Per rule: class-naam, uuid, condition (`applies()`), en de actions die op `apply()` gedaan worden.\n\n";
echo "---\n\n";

/** @var array<string, list<array{class: string, uuid: string, condition: string}>> */
$byVariable = [];
/** @var array<string, list<array{class: string, uuid: string, condition: string, value: bool}>> */
$byFieldHidden = [];
/** @var array<string, list<array{class: string, uuid: string, condition: string, value: bool}>> */
$byStepApplicable = [];

foreach ($ruleFiles as $file) {
    $class = basename($file, '.php');
    $content = file_get_contents($file);
    if ($content === false) {
        continue;
    }

    // Parse uuid uit identifier()-method: `return 'uuid-string';`
    preg_match('/identifier\([^)]*\): string\s*\{\s*return \'([^\']+)\'/', $content, $m);
    $uuid = $m[1] ?? '?';

    // Parse description uit @openforms-rule-description
    preg_match('/@openforms-rule-description\s*(.*?)\n\s*\*\//s', $content, $m);
    $description = trim($m[1] ?? '');
    // Multi-line description samenvouwen.
    $description = preg_replace('/\s*\*\s*/', ' ', $description);

    // Parse applies()-body — alles tussen `applies(...)` en de matching `}`.
    preg_match('/public function applies\([^)]*\): bool\s*\{\s*(.*?)\n\s*\}/s', $content, $m);
    $applies = trim($m[1] ?? '');
    // Strip de wrapper `return (bool) (...);` zodat we alleen de uitdrukking houden.
    $applies = preg_replace('/^return\s*\(?bool\)?\s*\((.*)\)\s*;$/s', '$1', $applies) ?? $applies;
    $applies = preg_replace('/^return\s*(.*?);$/s', '$1', $applies) ?? $applies;

    // Parse apply()-body
    preg_match('/public function apply\([^)]*\): void\s*\{\s*(.*?)\n\s*\}\s*\}\s*$/s', $content, $m);
    $apply = trim($m[1] ?? '');

    echo "## `{$class}`\n\n";
    echo "- **uuid**: `{$uuid}`\n";
    if ($description !== '') {
        echo '- **description**: '.$description."\n";
    }
    echo "- **condition**:\n  ```php\n  ".$applies."\n  ```\n";
    echo "- **actions**:\n";

    // Categoriseer per regel in apply().
    $lines = preg_split('/\n/', $apply) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        echo '  - `'.$line."`\n";

        // Indexeer per type voor de samenvattingen onderaan.
        if (preg_match("/setVariable\\('([^']+)'\\s*,\\s*(.*?)\\);/", $line, $mm)) {
            $byVariable[$mm[1]][] = [
                'class' => $class,
                'uuid' => $uuid,
                'condition' => $applies,
                'value_expr' => trim($mm[2]),
            ];
        } elseif (preg_match("/setFieldHidden\\('([^']+)'\\s*,\\s*(true|false)\\)/", $line, $mm)) {
            $byFieldHidden[$mm[1]][] = [
                'class' => $class,
                'uuid' => $uuid,
                'condition' => $applies,
                'value' => $mm[2] === 'true',
            ];
        } elseif (preg_match("/setStepApplicable\\('([^']+)'\\s*,\\s*(true|false)\\)/", $line, $mm)) {
            $byStepApplicable[$mm[1]][] = [
                'class' => $class,
                'uuid' => $uuid,
                'condition' => $applies,
                'value' => $mm[2] === 'true',
            ];
        }
    }
    echo "\n---\n\n";
}

echo "# Samenvattingen per doel\n\n";

echo "## Variabelen die door rules gezet worden\n\n";
echo "Per variabele: welke rules schrijven 'm + onder welke voorwaarde. Wordt input voor `FormDerivedState`-methodes.\n\n";
ksort($byVariable);
foreach ($byVariable as $name => $entries) {
    $cnt = count($entries);
    echo "### `{$name}` ({$cnt} writer".($cnt === 1 ? '' : 's').")\n\n";
    foreach ($entries as $e) {
        echo '- **'.$e['class'].'** (`'.$e['uuid']."`)\n";
        echo '  - `if ('.$e['condition']."): set to:`\n";
        $val = preg_replace('/\s+/', ' ', $e['value_expr']);
        echo '  - `'.substr($val, 0, 200).(strlen($val) > 200 ? '...' : '')."`\n";
    }
    echo "\n";
}

echo "## Velden die door rules verborgen worden\n\n";
ksort($byFieldHidden);
foreach ($byFieldHidden as $field => $entries) {
    echo "### `{$field}` (".count($entries).' rule'.(count($entries) === 1 ? '' : 's').")\n\n";
    foreach ($entries as $e) {
        $direction = $e['value'] ? 'hide' : 'show';
        echo '- **'.$e['class'].'** (`'.$e['uuid']."`) → {$direction}\n";
        echo '  - condition: `'.preg_replace('/\s+/', ' ', $e['condition'])."`\n";
    }
    echo "\n";
}

echo "## Stappen die door rules op niet-applicable gezet worden\n\n";
ksort($byStepApplicable);
foreach ($byStepApplicable as $stepUuid => $entries) {
    $applicable = array_values(array_filter($entries, fn ($e) => $e['value']));
    $notApplicable = array_values(array_filter($entries, fn ($e) => ! $e['value']));
    echo "### Step `{$stepUuid}`\n\n";
    if ($applicable !== []) {
        echo "**Wordt applicable door:**\n";
        foreach ($applicable as $e) {
            echo '- '.$e['class'].' — `'.preg_replace('/\s+/', ' ', $e['condition'])."`\n";
        }
        echo "\n";
    }
    if ($notApplicable !== []) {
        echo "**Wordt non-applicable door:**\n";
        foreach ($notApplicable as $e) {
            echo '- '.$e['class'].' — `'.preg_replace('/\s+/', ' ', $e['condition'])."`\n";
        }
        echo "\n";
    }
}

echo "\n*Einde inventaris*\n";
