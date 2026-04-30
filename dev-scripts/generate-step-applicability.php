<?php

declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel;

/**
 * Genereert FormStepApplicability-snippet uit alle setStepApplicable-
 * rules in de transpiled rule-files. Pendant van
 * generate-field-visibility.php, voor stap-zichtbaarheid i.p.v. veld-
 * zichtbaarheid.
 *
 * Run eenmalig tijdens de rules-engine-eliminatie. Daarna mag dit
 * bestand weg.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$dir = __DIR__.'/../app/EventForm/Rules';
$files = glob("$dir/*.php");
if (! $files) {
    fwrite(STDERR, "geen rule-files\n");
    exit(1);
}

$skip = ['Rule.php', 'RulesEngine.php', 'RuleRegistry.php'];
// Hand-geschreven rules: laten we apart, integreren we straks
// hand-matig in FormStepApplicability.
$handgeschrevenSkip = ['VergunningSchakeltMeldingUit.php', 'MeldingSchakeltVergunningstappenUit.php'];

/** @var array<string, list<array{uuid: string, condition: string, applicable: bool}>> */
$byStep = [];

foreach ($files as $file) {
    if (in_array(basename($file), array_merge($skip, $handgeschrevenSkip), true)) {
        continue;
    }
    $content = (string) file_get_contents($file);

    preg_match('/identifier\([^)]*\): string\s*\{\s*return \'([^\']+)\'/', $content, $m);
    $uuid = $m[1] ?? '';

    preg_match('/public function applies\([^)]*\): bool\s*\{\s*return\s*(.+?);\s*\}/s', $content, $m);
    $rawApplies = trim($m[1] ?? '');
    $rawApplies = preg_replace('/^\(bool\)\s*\((.+)\)$/s', '$1', $rawApplies) ?? $rawApplies;

    if (! preg_match_all("/setStepApplicable\\('([^']+)'\\s*,\\s*(true|false)\\)/", $content, $m, PREG_SET_ORDER)) {
        continue;
    }

    foreach ($m as $match) {
        $stepUuid = $match[1];
        $applicable = $match[2] === 'true';
        $byStep[$stepUuid][] = [
            'uuid' => $uuid,
            'condition' => $rawApplies,
            'applicable' => $applicable,
        ];
    }
}

ksort($byStep);

echo "// === GEGENEREERD via dev-scripts/generate-step-applicability.php ===\n";
echo '// Aantal stappen met applicability-rules: '.count($byStep)."\n\n";

echo "/** @var array<string, true> */\n";
echo "public const COMPUTED_STEPS = [\n";
foreach (array_keys($byStep) as $stepUuid) {
    echo "    '{$stepUuid}' => true,\n";
}
echo "];\n\n";

echo "public function get(string \$stepUuid): ?bool\n{\n    \$s = \$this->state;\n\n    return match (\$stepUuid) {\n";

foreach ($byStep as $stepUuid => $rules) {
    // Verzamel show/hide condities. `applicable=false` = stap niet-toepasselijk.
    $hideConditions = [];   // applicable=false
    $showConditions = [];   // applicable=true
    foreach ($rules as $r) {
        if ($r['applicable']) {
            $showConditions[] = $r['condition'];
        } else {
            $hideConditions[] = $r['condition'];
        }
    }

    echo "        '{$stepUuid}' => (function () use (\$s): ?bool {\n";

    // Comment-block met de origin-rules.
    echo "            // OF-rules:\n";
    foreach ($rules as $r) {
        $direction = $r['applicable'] ? 'applicable' : 'NOT applicable';
        echo "            //   - {$r['uuid']} → {$direction} wanneer: ".substr($r['condition'], 0, 120)."\n";
    }

    if ($showConditions !== []) {
        echo '            if ('.implode(' || ', array_map(fn ($c) => '('.$c.')', $showConditions)).") {\n";
        echo "                return true; // applicable\n            }\n";
    }
    if ($hideConditions !== []) {
        echo '            if ('.implode(' || ', array_map(fn ($c) => '('.$c.')', $hideConditions)).") {\n";
        echo "                return false; // not applicable\n            }\n";
    }
    echo "            return null; // door-fall: default applicable\n";
    echo "        })(),\n";
}
echo "        default => null,\n    };\n}\n";
