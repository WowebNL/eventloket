<?php

declare(strict_types=1);

use App\EventForm\Rules\Rule;
use App\EventForm\Rules\RuleRegistry;
use Illuminate\Support\Facades\File;

/**
 * Bestanden die NIET door de transpiler worden gegenereerd, maar wél in
 * `app/EventForm/Rules/` leven en bij elke test-run + transpile-run
 * behouden moeten blijven. Synchroon met de constante
 * `TranspileEventForm::HANDGESCHREVEN_RULES`.
 */
const BEHOUDEN_BESTANDEN = [
    'Rule.php',
    'RulesEngine.php',
    'VergunningSchakeltMeldingUit.php',
    'MeldingSchakeltVergunningstappenUit.php',
];

beforeEach(function () {
    // Target-directories opschonen zodat we een schone re-run testen.
    $this->rulesDir = base_path('app/EventForm/Rules');
    $this->stepsDir = base_path('app/EventForm/Schema/Steps');

    foreach (File::files($this->rulesDir) as $file) {
        if (! in_array($file->getFilename(), BEHOUDEN_BESTANDEN, true)) {
            File::delete($file->getRealPath());
        }
    }
    if (File::isDirectory($this->stepsDir)) {
        File::deleteDirectory($this->stepsDir);
    }
});

test('transpile:event-form generates 144 rule classes + 17 step classes from local dump', function () {
    $this->artisan('transpile:event-form', ['--source' => 'local', '--force' => true])
        ->assertSuccessful();

    $ruleFiles = collect(File::files(base_path('app/EventForm/Rules')))
        ->reject(fn ($f) => in_array($f->getFilename(), [...BEHOUDEN_BESTANDEN, 'RuleRegistry.php'], true))
        ->count();

    $stepFiles = File::isDirectory(base_path('app/EventForm/Schema/Steps'))
        ? count(File::files(base_path('app/EventForm/Schema/Steps')))
        : 0;

    expect($ruleFiles)->toBe(144)
        ->and($stepFiles)->toBe(17);
});

test('RuleRegistry is generated and lists all rule classes (144 transpiled + 2 handgeschreven)', function () {
    $this->artisan('transpile:event-form', ['--source' => 'local', '--force' => true])
        ->assertSuccessful();

    $registryPath = base_path('app/EventForm/Rules/RuleRegistry.php');
    expect(File::exists($registryPath))->toBeTrue();

    // Force re-load van het zojuist (her)gegenereerde bestand zodat we de
    // verse class-list testen, niet een eventueel eerder geladen versie.
    require_once $registryPath;

    $registered = RuleRegistry::all();

    // 144 getranspileerde + 2 handgeschreven (Vergunning/Melding-step-applicability).
    expect($registered)
        ->toHaveCount(146)
        ->each->toBeString();

    foreach ($registered as $fqcn) {
        expect(class_exists($fqcn))->toBeTrue("RuleRegistry references missing class: {$fqcn}");
        $reflection = new ReflectionClass($fqcn);
        expect($reflection->implementsInterface(Rule::class))
            ->toBeTrue("RuleRegistry-class {$fqcn} implements Rule niet");
    }

    expect(RuleRegistry::count())->toBe(146);
});

test('generated files are syntactically valid PHP', function () {
    $this->artisan('transpile:event-form', ['--source' => 'local', '--force' => true])
        ->assertSuccessful();

    $invalid = [];
    foreach (
        [
            base_path('app/EventForm/Rules'),
            base_path('app/EventForm/Schema/Steps'),
        ] as $dir
    ) {
        if (! File::isDirectory($dir)) {
            continue;
        }
        foreach (File::files($dir) as $file) {
            if (in_array($file->getFilename(), BEHOUDEN_BESTANDEN, true)) {
                continue;
            }
            $output = [];
            $exitCode = 0;
            exec('php -l '.escapeshellarg($file->getRealPath()).' 2>&1', $output, $exitCode);
            if ($exitCode !== 0) {
                $invalid[] = $file->getFilename().': '.implode(' | ', $output);
            }
        }
    }

    expect($invalid)->toBe([]);
});
