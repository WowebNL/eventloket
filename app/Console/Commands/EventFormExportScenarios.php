<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use ReflectionClass;
use Tests\Feature\EventForm\Equivalence\Scenarios\ScenarioProvider;

/**
 * Exporteert alle gedefinieerde equivalentie-scenarios als JSON zodat externe
 * tools (zoals de Node-gebaseerde json-logic-js verificator) ze kunnen lezen.
 * Zo blijven de scenarios op één plek gedefinieerd (PHP-providers) en toch
 * beschikbaar voor alternatieve runtime-checks.
 */
class EventFormExportScenarios extends Command
{
    protected $signature = 'eventform:export-scenarios
        {--out=tests/Feature/EventForm/Equivalence/scenarios.json : Bestand waar de export heengaat}';

    protected $description = 'Exporteert alle scenario-providers naar één JSON-bestand voor externe verificatie';

    public function handle(): int
    {
        $dir = base_path('tests/Feature/EventForm/Equivalence/Scenarios');
        if (! is_dir($dir)) {
            $this->error('Scenarios-directory niet gevonden');

            return self::FAILURE;
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

        $out = [];
        foreach ($providers as $provider) {
            foreach ($provider::all() as $label => $entry) {
                $scenario = $entry[0];
                $out[] = [
                    'provider' => $provider,
                    'label' => $label,
                    'scenario' => $scenario,
                ];
            }
        }

        $outPath = base_path((string) $this->option('out'));
        @mkdir(dirname($outPath), 0755, true);
        file_put_contents(
            $outPath,
            json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
        );

        $this->info("{$outPath}: ".count($out).' scenarios geëxporteerd');

        return self::SUCCESS;
    }
}
