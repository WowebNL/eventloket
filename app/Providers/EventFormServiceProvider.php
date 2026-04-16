<?php

declare(strict_types=1);

namespace App\Providers;

use App\EventForm\Rules\Rule;
use App\EventForm\Rules\RulesEngine;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

/**
 * Registreert de RulesEngine met alle gegenereerde Rule-klassen uit
 * `app/EventForm/Rules/`. Via reflection-scan zodat nieuwe rules (na een
 * `transpile:event-form`-run) automatisch meegenomen worden zonder
 * handmatige registratie.
 */
class EventFormServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RulesEngine::class, function (): RulesEngine {
            return new RulesEngine($this->discoverRules());
        });
    }

    /**
     * @return list<Rule>
     */
    private function discoverRules(): array
    {
        $dir = app_path('EventForm/Rules');
        if (! is_dir($dir)) {
            return [];
        }

        $rules = [];
        foreach (File::files($dir) as $file) {
            $name = $file->getFilenameWithoutExtension();
            if (in_array($name, ['Rule', 'RulesEngine'], true)) {
                continue;
            }
            $fqcn = 'App\\EventForm\\Rules\\'.$name;
            if (! class_exists($fqcn)) {
                continue;
            }
            $reflection = new \ReflectionClass($fqcn);
            if (! $reflection->implementsInterface(Rule::class) || $reflection->isAbstract()) {
                continue;
            }
            $rules[] = $this->app->make($fqcn);
        }

        return $rules;
    }
}
