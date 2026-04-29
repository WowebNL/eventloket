<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\EventForm\Transpiler\RuleClassGenerator;
use App\EventForm\Transpiler\RuleDependencyAnalyzer;
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

        // Regenerate rules-folder: wipe generated files, keep handwritten Rule.php +
        // RulesEngine.php én alle handgeschreven aanvullingen (zie de
        // HANDGESCHREVEN_RULES-constante hieronder). RuleRegistry.php is wel
        // gegenereerd en mag mee in de wipe.
        $behoudenFiles = array_merge(
            ['Rule.php', 'RulesEngine.php'],
            array_map(static fn ($cls): string => "{$cls}.php", self::HANDGESCHREVEN_RULES),
        );
        File::ensureDirectoryExists($rulesDir);
        foreach (File::files($rulesDir) as $file) {
            if (! in_array($file->getFilename(), $behoudenFiles, true)) {
                File::delete($file->getRealPath());
            }
        }
        if (File::isDirectory($stepsDir)) {
            File::deleteDirectory($stepsDir);
        }
        File::ensureDirectoryExists($stepsDir);

        // Bouw per-stap de set veld-keys die op die stap wonen. Nodig voor
        // het bepalen van trigger-scope + effect-scope per gegenereerde rule.
        $stepFieldIndex = $this->buildStepFieldIndex($raw->formSteps);

        $ruleGen = (new RuleClassGenerator)->withStepFieldIndex($stepFieldIndex);
        $ruleCount = 0;
        $ruleClassNames = [];
        foreach ($raw->logicRules as $rule) {
            $generated = $ruleGen->generate($rule);
            File::put("{$rulesDir}/{$generated->className}.php", $generated->fileContent);
            $ruleClassNames[] = $generated->className;
            $ruleCount++;
        }

        // Schrijf RuleRegistry.php met expliciete `::class`-references zodat
        // PhpStorm de gegenereerde rules ziet als gebruikt + de runtime-
        // discovery niet langer afhankelijk is van filesystem-scans.
        sort($ruleClassNames);
        File::put("{$rulesDir}/RuleRegistry.php", $this->renderRuleRegistry($ruleClassNames));

        // Bouw eerst de globale field-type index over alle stappen zodat
        // selectboxes-conditionals die naar een veld in een andere step
        // wijzen, correct als dot-access (`$get('X.key')`) worden geëmit.
        $fieldTypeIndex = $this->buildFieldTypeIndex($raw->formSteps);
        // Set keys die ergens een trigger vormen, komen uit 3 bronnen:
        //  - directe `conditional.when` op een ander component
        //  - rule-triggers (JsonLogic leest een veld)
        //  - template-interpolaties `{{ veld }}` in labels of content-blokken
        //    (Filament rendert pas opnieuw bij een server-roundtrip, dus
        //    anders blijft een overzicht met {{ OpbouwStart }} etc. leeg
        //    tot de user op Volgende drukt).
        // Al deze velden moeten `->live()` krijgen zodat Filament direct
        // z'n visibility-closures, onze rules, én de interpolated labels
        // opnieuw evalueert bij state-change.
        $triggerKeys = array_values(array_unique(array_merge(
            $this->collectTriggerKeys($raw->formSteps),
            $this->collectRuleTriggerKeys($raw->logicRules),
            $this->collectTemplateInterpolationKeys($raw->formSteps),
        )));

        // Component-keys die niks meer toevoegen in onze sync-prefill-setup
        // (OF gebruikte ze als placeholder tijdens async fetches) maar wel
        // altijd in beeld blijven. Weghalen is veiliger dan verbergen —
        // geen kans dat ze via een stale conditional tóch terugkomen.
        $skipKeys = [
            'loadUserInformation',
        ];

        $stepGen = (new StepSchemaGenerator)
            ->withFieldTypeIndex($fieldTypeIndex)
            ->withTriggerKeys($triggerKeys)
            ->withVariableInitialValues($this->buildVariableInitialValues($raw->variables))
            ->withSkipKeys($skipKeys);
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

    /**
     * @param  list<array<string, mixed>>  $steps
     * @return array<string, string> veld-key → type
     */
    private function buildFieldTypeIndex(array $steps): array
    {
        $index = [];
        foreach ($steps as $step) {
            $components = $step['configuration']['components'] ?? [];
            if (is_array($components)) {
                /** @var list<array<string, mixed>> $components */
                $this->walkForTypes($components, $index);
            }
        }

        return $index;
    }

    /**
     * @param  list<array<string, mixed>>  $components
     * @param  array<string, string>  $index
     */
    private function walkForTypes(array $components, array &$index): void
    {
        foreach ($components as $component) {
            $key = $component['key'] ?? null;
            $type = $component['type'] ?? null;
            if (is_string($key) && $key !== '' && is_string($type)) {
                $index[$key] = $type;
            }
            if (isset($component['components']) && is_array($component['components'])) {
                /** @var list<array<string, mixed>> $nested */
                $nested = $component['components'];
                $this->walkForTypes($nested, $index);
            }
            if (($component['type'] ?? null) === 'columns' && is_array($component['columns'] ?? null)) {
                foreach ($component['columns'] as $column) {
                    if (is_array($column) && is_array($column['components'] ?? null)) {
                        /** @var list<array<string, mixed>> $nested */
                        $nested = $column['components'];
                        $this->walkForTypes($nested, $index);
                    }
                }
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $variables
     * @return array<string, mixed> key → initial_value
     */
    private function buildVariableInitialValues(array $variables): array
    {
        $map = [];
        foreach ($variables as $variable) {
            $key = $variable['key'] ?? null;
            if (is_string($key) && $key !== '' && array_key_exists('initial_value', $variable)) {
                $map[$key] = $variable['initial_value'];
            }
        }

        return $map;
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     * @return array<string, list<string>> stepUuid → lijst van veld-keys op die stap
     */
    private function buildStepFieldIndex(array $steps): array
    {
        $index = [];
        foreach ($steps as $step) {
            $uuid = (string) ($step['uuid'] ?? '');
            if ($uuid === '') {
                continue;
            }
            $components = $step['configuration']['components'] ?? [];
            if (! is_array($components)) {
                continue;
            }
            /** @var list<array<string, mixed>> $components */
            $keys = [];
            $this->walkForKeys($components, $keys);
            $index[$uuid] = $keys;
        }

        return $index;
    }

    /**
     * @param  list<array<string, mixed>>  $components
     * @param  list<string>  $keys
     */
    private function walkForKeys(array $components, array &$keys): void
    {
        foreach ($components as $component) {
            $key = $component['key'] ?? null;
            if (is_string($key) && $key !== '') {
                $keys[] = $key;
            }
            if (isset($component['components']) && is_array($component['components'])) {
                /** @var list<array<string, mixed>> $nested */
                $nested = $component['components'];
                $this->walkForKeys($nested, $keys);
            }
            if (($component['type'] ?? null) === 'columns' && is_array($component['columns'] ?? null)) {
                foreach ($component['columns'] as $column) {
                    if (is_array($column) && is_array($column['components'] ?? null)) {
                        /** @var list<array<string, mixed>> $nested */
                        $nested = $column['components'];
                        $this->walkForKeys($nested, $keys);
                    }
                }
            }
        }
    }

    /**
     * Verzamel de veld-keys die in de triggers van logic-rules als
     * `{var: X}` voorkomen. Alleen de root-key telt — sub-paden zijn van
     * de geneste structuur van die root af te leiden.
     *
     * @param  list<array<string, mixed>>  $rules
     * @return list<string>
     */
    private function collectRuleTriggerKeys(array $rules): array
    {
        $analyzer = new RuleDependencyAnalyzer;
        $keys = [];
        foreach ($rules as $rule) {
            foreach ($analyzer->readKeys($rule['json_logic_trigger'] ?? null) as $k) {
                $keys[$k] = true;
            }
        }

        return array_keys($keys);
    }

    /**
     * Scan alle labels, content-HTML en Jinja-templates in de form-steps
     * op `{{ veld }}`-interpolaties. Die velden moeten `->live()` krijgen
     * zodat een overzicht als "OpbouwStart: {{ OpbouwStart }}" reactief
     * wordt bij het invullen, i.p.v. leeg te blijven tot de user verder
     * navigeert.
     *
     * @param  list<array<string, mixed>>  $steps
     * @return list<string>
     */
    private function collectTemplateInterpolationKeys(array $steps): array
    {
        /** @var array<string, true> $keys */
        $keys = [];
        foreach ($steps as $step) {
            $components = $step['configuration']['components'] ?? [];
            if (is_array($components)) {
                /** @var list<array<string, mixed>> $components */
                $this->walkForTemplateVars($components, $keys);
            }
        }

        return array_keys($keys);
    }

    /**
     * @param  list<array<string, mixed>>  $components
     * @param  array<string, true>  $keys
     */
    private function walkForTemplateVars(array $components, array &$keys): void
    {
        foreach ($components as $component) {
            // Check alle velden waarin een gebruiker-interpoleerbare
            // template kan staan. Zowel OF's `label`, het `description`,
            // als de body van content-components (`html`).
            foreach (['label', 'description', 'html'] as $field) {
                $value = $component[$field] ?? null;
                if (is_string($value) && $value !== '') {
                    $this->extractInterpolatedKeys($value, $keys);
                }
            }

            if (isset($component['components']) && is_array($component['components'])) {
                /** @var list<array<string, mixed>> $nested */
                $nested = $component['components'];
                $this->walkForTemplateVars($nested, $keys);
            }
            if (($component['type'] ?? null) === 'columns' && is_array($component['columns'] ?? null)) {
                foreach ($component['columns'] as $column) {
                    if (is_array($column) && is_array($column['components'] ?? null)) {
                        /** @var list<array<string, mixed>> $nested */
                        $nested = $column['components'];
                        $this->walkForTemplateVars($nested, $keys);
                    }
                }
            }
        }
    }

    /**
     * Haal uit een template-string alle top-level veld-keys uit `{{ … }}`-
     * expressies. Ondersteunt zowel eenvoudig `{{ naam }}` als dot-access
     * `{{ evenementInGemeente.brk_identification }}` (we nemen dan de
     * root-key), plus filters `{{ lijst|join:", " }}` (alleen de key).
     *
     * @param  array<string, true>  $keys
     */
    private function extractInterpolatedKeys(string $template, array &$keys): void
    {
        if (preg_match_all('/\{\{\s*([a-zA-Z_][\w.]*)\s*(?:\|[^}]+)?\}\}/', $template, $matches) === false) {
            return;
        }
        foreach ($matches[1] ?? [] as $varPath) {
            $root = explode('.', $varPath)[0];
            // Sommige templates gebruiken systeem-keys die geen form-
            // veld zijn (bv. eventloketSession). Die staan niet in
            // ons field-type-index en krijgen geen ->live(). We nemen
            // ze hier wel mee; de stepgenerator negeert onbekende keys.
            if ($root !== '' && ! in_array($root, ['True', 'False', 'None'], true)) {
                $keys[$root] = true;
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     * @return list<string>
     */
    private function collectTriggerKeys(array $steps): array
    {
        /** @var array<string, true> $keys */
        $keys = [];
        foreach ($steps as $step) {
            $components = $step['configuration']['components'] ?? [];
            if (is_array($components)) {
                /** @var list<array<string, mixed>> $components */
                $this->walkForTriggers($components, $keys);
            }
        }

        return array_keys($keys);
    }

    /**
     * @param  list<array<string, mixed>>  $components
     * @param  array<string, true>  $keys
     */
    private function walkForTriggers(array $components, array &$keys): void
    {
        foreach ($components as $component) {
            $conditional = $component['conditional'] ?? null;
            if (is_array($conditional) && is_string($conditional['when'] ?? null) && $conditional['when'] !== '') {
                $keys[$conditional['when']] = true;
            }
            if (isset($component['components']) && is_array($component['components'])) {
                /** @var list<array<string, mixed>> $nested */
                $nested = $component['components'];
                $this->walkForTriggers($nested, $keys);
            }
            if (($component['type'] ?? null) === 'columns' && is_array($component['columns'] ?? null)) {
                foreach ($component['columns'] as $column) {
                    if (is_array($column) && is_array($column['components'] ?? null)) {
                        /** @var list<array<string, mixed>> $nested */
                        $nested = $column['components'];
                        $this->walkForTriggers($nested, $keys);
                    }
                }
            }
        }
    }

    /**
     * Handgeschreven Rule-classes die NIET door de transpiler worden
     * gegenereerd, maar wel altijd in de RuleRegistry moeten staan
     * zodat de RulesEngine ze ook draait. Wanneer je hier een nieuwe
     * regel toevoegt: zorg dat de class-naam de hele path heeft (zonder
     * `::class`) en commit deze command-wijziging samen met de Rule
     * zelf, anders gooit een volgende transpile-run 'm uit het registry.
     *
     * @var list<string>
     */
    private const HANDGESCHREVEN_RULES = [
        'VergunningSchakeltMeldingUit',
        'MeldingSchakeltVergunningstappenUit',
    ];

    /**
     * @param  list<string>  $classNames
     */
    private function renderRuleRegistry(array $classNames): string
    {
        $alle = array_merge($classNames, self::HANDGESCHREVEN_RULES);
        $lines = array_map(
            static fn (string $name): string => "            {$name}::class,",
            $alle,
        );
        $body = implode("\n", $lines);
        $count = count($alle);

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\\EventForm\\Rules;

        /**
         * Auto-gegenereerd door `php artisan transpile:event-form`. Niet handmatig
         * aanpassen — wijzigingen worden bij de volgende transpile-run overschreven.
         *
         * Dit registry maakt twee dingen mogelijk:
         *  - PhpStorm en andere static-analysis tools zien expliciete
         *    `::class`-references naar elke Rule, zodat ze niet als "no usages"
         *    gemarkeerd worden.
         *  - `EventFormServiceProvider` bouwt z'n RulesEngine vanuit deze
         *    deterministische lijst i.p.v. een filesystem-scan.
         *
         * De lijst bevat zowel de getranspileerde JsonLogic-rules als een aantal
         * handgeschreven aanvullingen voor gedrag dat in OF in de form-config
         * zat (niet in de logic-rules) — zie de `HANDGESCHREVEN_RULES`-constante
         * in `TranspileEventForm`.
         */
        final class RuleRegistry
        {
            /** @return list<class-string<Rule>> */
            public static function all(): array
            {
                return [
        {$body}
                ];
            }

            public static function count(): int
            {
                return {$count};
            }
        }

        PHP;
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
