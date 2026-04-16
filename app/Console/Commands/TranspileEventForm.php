<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\EventForm\Transpiler\RuleClassGenerator;
use App\EventForm\Transpiler\StepSchemaGenerator;
use App\Services\OpenForms\Veldenkaart\Loaders\ApiLoader;
use App\Services\OpenForms\Veldenkaart\Loaders\LoaderInterface;
use App\Services\OpenForms\Veldenkaart\Loaders\LocalLoader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TranspileEventForm extends Command
{
    protected $signature = 'transpile:event-form
        {--source=local : api|local — data source}
        {--form= : Form slug or UUID (defaults to services.open_forms.main_form_slug)}
        {--local-path= : Path to the local dump directory}
        {--force : Overwrite generated files without prompting}';

    protected $description = 'Transpile Open Forms logic rules and steps to PHP classes in app/EventForm/';

    public function handle(): int
    {
        $source = (string) $this->option('source');
        $form = (string) ($this->option('form') ?: config('services.open_forms.main_form_slug') ?: '');
        if ($form === '') {
            $this->error('Missing --form argument and no services.open_forms.main_form_slug configured');

            return self::INVALID;
        }

        $loader = $this->buildLoader($source);
        if ($loader === null) {
            return self::INVALID;
        }

        $rulesDir = base_path('app/EventForm/Rules');
        $stepsDir = base_path('app/EventForm/Schema/Steps');

        if (! $this->option('force') && ! $this->confirmOverwrite($rulesDir, $stepsDir)) {
            $this->warn('Aborted.');

            return self::FAILURE;
        }

        $this->info("Loading form '{$form}' from {$loader->sourceLabel()}...");
        $raw = $loader->load($form);

        $this->info('Transpiling '.count($raw->logicRules).' rules + '.count($raw->formSteps).' steps...');

        // Regenerate rules-folder: wipe generated files, keep handwritten Rule.php + RulesEngine.php.
        File::ensureDirectoryExists($rulesDir);
        foreach (File::files($rulesDir) as $file) {
            if (! in_array($file->getFilename(), ['Rule.php', 'RulesEngine.php'], true)) {
                File::delete($file->getRealPath());
            }
        }
        if (File::isDirectory($stepsDir)) {
            File::deleteDirectory($stepsDir);
        }
        File::ensureDirectoryExists($stepsDir);

        $ruleGen = new RuleClassGenerator;
        $ruleCount = 0;
        foreach ($raw->logicRules as $rule) {
            $generated = $ruleGen->generate($rule);
            File::put("{$rulesDir}/{$generated->className}.php", $generated->fileContent);
            $ruleCount++;
        }

        $stepGen = new StepSchemaGenerator;
        $stepCount = 0;
        foreach ($raw->formSteps as $step) {
            $generated = $stepGen->generate($step);
            File::put("{$stepsDir}/{$generated->className}.php", $generated->fileContent);
            $stepCount++;
        }

        $this->info("Wrote {$ruleCount} rules → app/EventForm/Rules/");
        $this->info("Wrote {$stepCount} steps → app/EventForm/Schema/Steps/");

        // Run Pint on the generated output to keep style consistent.
        $this->line('Running pint on generated files...');
        $pintExit = null;
        passthru('cd '.escapeshellarg(base_path()).' && ./vendor/bin/pint app/EventForm/Rules app/EventForm/Schema/Steps 2>&1 | tail -5', $pintExit);

        return self::SUCCESS;
    }

    private function buildLoader(string $source): ?LoaderInterface
    {
        if ($source === 'api') {
            $baseUrl = (string) config('services.open_forms.base_url');
            $token = (string) config('services.open_forms.admin_token');
            if ($baseUrl === '' || $token === '') {
                $this->error('services.open_forms.base_url / admin_token not configured');

                return null;
            }

            return new ApiLoader($baseUrl, $token);
        }
        if ($source === 'local') {
            $path = (string) ($this->option('local-path') ?: base_path('docker/local-data/open-formulier'));

            return new LocalLoader($path);
        }

        $this->error("Unknown --source value: {$source} (expected: api|local)");

        return null;
    }

    private function confirmOverwrite(string $rulesDir, string $stepsDir): bool
    {
        $existingRules = File::isDirectory($rulesDir)
            ? collect(File::files($rulesDir))
                ->reject(fn ($f): bool => in_array($f->getFilename(), ['Rule.php', 'RulesEngine.php'], true))
                ->count()
            : 0;
        $existingSteps = File::isDirectory($stepsDir) ? count(File::files($stepsDir)) : 0;

        if ($existingRules === 0 && $existingSteps === 0) {
            return true;
        }

        return $this->confirm(
            "Dit overschrijft {$existingRules} bestaande rule-files + {$existingSteps} step-files. Doorgaan?",
            false,
        );
    }
}
