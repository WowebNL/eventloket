<?php

declare(strict_types=1);

/**
 * Identificeert welke transpiled rule-files volledig gemigreerd zijn —
 * d.w.z. alle hun acties zijn gedekt door FormDerivedState,
 * FormFieldVisibility, of FormStepApplicability.
 *
 * Output: lijst van class-namen die zonder gedragsverandering verwijderd
 * mogen worden uit `RuleRegistry`. Print ook welke rules NIET volledig
 * gedekt zijn met de reden (welke action niet gemigreerd is).
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\EventForm\State\FormDerivedState;
use App\EventForm\State\FormFieldVisibility;
use App\EventForm\State\FormStepApplicability;
use Illuminate\Contracts\Console\Kernel;

$dir = __DIR__.'/../app/EventForm/Rules';
$files = glob("$dir/*.php");
$skip = ['Rule.php', 'RulesEngine.php', 'RuleRegistry.php', 'VergunningSchakeltMeldingUit.php', 'MeldingSchakeltVergunningstappenUit.php'];

$dead = [];
$alive = [];

foreach ($files as $file) {
    $base = basename($file);
    if (in_array($base, $skip, true)) {
        continue;
    }
    $content = (string) file_get_contents($file);

    // Pak alle acties in apply().
    preg_match('/public function apply\([^)]*\): void\s*\{(.*?)\n\s*\}\s*\}\s*$/s', $content, $m);
    $applyBody = $m[1] ?? '';

    $reasons = [];
    $hasActions = false;

    // setVariable($key, ...)
    if (preg_match_all("/setVariable\\('([^']+)'/", $applyBody, $mm)) {
        $hasActions = true;
        foreach ($mm[1] as $key) {
            if (! isset(FormDerivedState::COMPUTED_KEYS[$key])) {
                $reasons[] = "setVariable('{$key}') — niet in FormDerivedState";
            }
        }
    }
    // setFieldHidden($key, ...)
    if (preg_match_all("/setFieldHidden\\('([^']+)'/", $applyBody, $mm)) {
        $hasActions = true;
        foreach ($mm[1] as $key) {
            if (! isset(FormFieldVisibility::COMPUTED_KEYS[$key])) {
                $reasons[] = "setFieldHidden('{$key}') — niet in FormFieldVisibility";
            }
        }
    }
    // setStepApplicable($uuid, ...)
    if (preg_match_all("/setStepApplicable\\('([^']+)'/", $applyBody, $mm)) {
        $hasActions = true;
        foreach ($mm[1] as $uuid) {
            if (! isset(FormStepApplicability::COMPUTED_STEPS[$uuid])) {
                $reasons[] = "setStepApplicable('{$uuid}') — niet in FormStepApplicability";
            }
        }
    }
    // fetch-from-service / setSystem — NIET gedekt door pure-classes.
    if (str_contains($applyBody, 'ServiceFetcher')) {
        $reasons[] = 'fetch-from-service — nog niet gemigreerd';
        $hasActions = true;
    }
    if (str_contains($applyBody, "setSystem('registration_backend'")) {
        // Gemigreerd naar FormSystemDerivedState::registrationBackend()
        // — engine-write redundant, FormState delegeert via system.X-pad.
        $hasActions = true;
    } elseif (str_contains($applyBody, 'setSystem')) {
        $reasons[] = 'setSystem (anders dan registration_backend) — nog niet gemigreerd';
        $hasActions = true;
    }

    $className = basename($file, '.php');
    if (! $hasActions) {
        // Lege apply (geen actie). Veiligheidshalve: geen blocker.
        $dead[] = $className;
    } elseif ($reasons === []) {
        $dead[] = $className;
    } else {
        $alive[$className] = $reasons;
    }
}

sort($dead);
ksort($alive);

echo "# Dead rules (alle acties gedekt door pure-classes)\n\n";
echo 'Aantal: '.count($dead)."\n\n";
foreach ($dead as $c) {
    echo "- {$c}\n";
}

echo "\n# Nog levende rules (één of meer acties niet gemigreerd)\n\n";
echo 'Aantal: '.count($alive)."\n\n";
foreach ($alive as $c => $reasons) {
    echo "## {$c}\n";
    foreach ($reasons as $r) {
        echo "  - {$r}\n";
    }
    echo "\n";
}
