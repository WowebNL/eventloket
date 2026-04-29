<?php

declare(strict_types=1);

/**
 * One-shot migratie-script: leest alle transpiled rule-files en bouwt
 * een statisch overzicht van setFieldHidden-rules per veld. Output is
 * een PHP-snippet dat we in `FormFieldVisibility::computeFor()`
 * plakken om alle setFieldHidden-rules naar pure-functioneel om te
 * zetten.
 *
 * Wordt eenmalig gerund tijdens de rules-engine-eliminatie. Daarna
 * mag dit bestand weg.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$dir = __DIR__.'/../app/EventForm/Rules';
$files = glob("$dir/*.php");
if (! $files) {
    fwrite(STDERR, "geen rule-files\n");
    exit(1);
}

$skip = ['Rule.php', 'RulesEngine.php', 'RuleRegistry.php', 'VergunningSchakeltMeldingUit.php', 'MeldingSchakeltVergunningstappenUit.php'];

/** @var array<string, list<array{uuid: string, condition: string, hidden: bool}>> */
$byField = [];

foreach ($files as $file) {
    if (in_array(basename($file), $skip, true)) {
        continue;
    }
    $content = (string) file_get_contents($file);

    // Pak het uuid.
    preg_match('/identifier\([^)]*\): string\s*\{\s*return \'([^\']+)\'/', $content, $m);
    $uuid = $m[1] ?? '';

    // Pak applies()-body als een PHP-uitdrukking.
    preg_match('/public function applies\([^)]*\): bool\s*\{\s*return\s*(.+?);\s*\}/s', $content, $m);
    $rawApplies = trim($m[1] ?? '');
    // Strip het buitenste `(bool) (...)`-omhulsel.
    $rawApplies = preg_replace('/^\(bool\)\s*\((.+)\)$/s', '$1', $rawApplies) ?? $rawApplies;

    // Vind alle setFieldHidden-acties.
    if (! preg_match_all("/setFieldHidden\\('([^']+)'\\s*,\\s*(true|false)\\)/", $content, $m, PREG_SET_ORDER)) {
        continue;
    }

    foreach ($m as $match) {
        $field = $match[1];
        $hidden = $match[2] === 'true';
        $byField[$field][] = [
            'uuid' => $uuid,
            'condition' => $rawApplies,
            'hidden' => $hidden,
        ];
    }
}

// Sorteer alfabetisch op veld voor stabiele diff bij hergeneratie.
ksort($byField);

echo "// === GEGENEREERD via dev-scripts/generate-field-visibility.php ===\n";
echo "// Aantal velden: ".count($byField)."\n\n";

echo "/** @var array<string, true> */\n";
echo "public const COMPUTED_KEYS = [\n";
foreach (array_keys($byField) as $field) {
    echo "    '".addslashes($field)."' => true,\n";
}
echo "];\n\n";

echo "public function get(string \$key): ?bool\n{\n    return match (\$key) {\n";
foreach (array_keys($byField) as $field) {
    $methodSafe = preg_replace('/[^a-zA-Z0-9_]/', '_', $field);
    echo "        '".addslashes($field)."' => \$this->{$methodSafe}(),\n";
}
echo "        default => null,\n    };\n}\n\n";

foreach ($byField as $field => $rules) {
    $methodSafe = preg_replace('/[^a-zA-Z0-9_]/', '_', $field);
    echo "/**\n";
    echo " * `{$field}`-veld zichtbaarheid.\n";
    foreach ($rules as $r) {
        $action = $r['hidden'] ? 'hide' : 'show';
        echo " *  - OF-rule {$r['uuid']} → {$action} wanneer: {$r['condition']}\n";
    }
    echo " */\n";
    echo "public function {$methodSafe}(): ?bool\n{\n    \$s = \$this->state;\n";

    // Effect-volgorde: rules in OF werden in alphabetical-class-name-volgorde
    // geëvalueerd. Last-write-wins via fixpoint. We doen dezelfde volgorde
    // hier expliciet door de rules die we hebben verzameld af te lopen en de
    // laatste matchende decisie te onthouden.
    $hideConditions = [];
    $showConditions = [];
    foreach ($rules as $r) {
        if ($r['hidden']) {
            $hideConditions[] = $r['condition'];
        } else {
            $showConditions[] = $r['condition'];
        }
    }

    // Show wint wanneer een show-rule matched (last-write-wins-equivalent in
    // de meeste gevallen waar show-rules komen NA hide-rules in
    // alphabetical-volgorde — voor edge-cases waar dat niet klopt
    // moet handmatige correctie). Anders, hide-rule matched? hide.
    if ($showConditions !== []) {
        echo '    if ('.implode(' || ', array_map(fn ($c) => '('.$c.')', $showConditions)).") {\n";
        echo "        return false; // show\n    }\n";
    }
    if ($hideConditions !== []) {
        echo '    if ('.implode(' || ', array_map(fn ($c) => '('.$c.')', $hideConditions)).") {\n";
        echo "        return true; // hide\n    }\n";
    }
    echo "    return null; // door-fall: default visibility uit step-file\n}\n\n";
}
