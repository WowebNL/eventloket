<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Target-directories opschonen zodat we een schone re-run testen.
    $this->rulesDir = base_path('app/EventForm/Rules');
    $this->stepsDir = base_path('app/EventForm/Schema/Steps');

    foreach (File::files($this->rulesDir) as $file) {
        if (! in_array($file->getFilename(), ['Rule.php', 'RulesEngine.php'], true)) {
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
        ->reject(fn ($f) => in_array($f->getFilename(), ['Rule.php', 'RulesEngine.php', 'RuleRegistry.php'], true))
        ->count();

    $stepFiles = File::isDirectory(base_path('app/EventForm/Schema/Steps'))
        ? count(File::files(base_path('app/EventForm/Schema/Steps')))
        : 0;

    expect($ruleFiles)->toBe(144)
        ->and($stepFiles)->toBe(17);
});

test('RuleRegistry is generated and lists all rule classes', function () {
    $this->artisan('transpile:event-form', ['--source' => 'local', '--force' => true])
        ->assertSuccessful();

    $registryPath = base_path('app/EventForm/Rules/RuleRegistry.php');
    expect(File::exists($registryPath))->toBeTrue();

    // Force re-load van het zojuist (her)gegenereerde bestand zodat we de
    // verse class-list testen, niet een eventueel eerder geladen versie.
    require_once $registryPath;

    $registered = \App\EventForm\Rules\RuleRegistry::all();

    expect($registered)
        ->toHaveCount(144)
        ->each->toBeString();

    foreach ($registered as $fqcn) {
        expect(class_exists($fqcn))->toBeTrue("RuleRegistry references missing class: {$fqcn}");
        $reflection = new ReflectionClass($fqcn);
        expect($reflection->implementsInterface(\App\EventForm\Rules\Rule::class))
            ->toBeTrue("RuleRegistry-class {$fqcn} implements Rule niet");
    }

    expect(\App\EventForm\Rules\RuleRegistry::count())->toBe(144);
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
            if (in_array($file->getFilename(), ['Rule.php', 'RulesEngine.php'], true)) {
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
