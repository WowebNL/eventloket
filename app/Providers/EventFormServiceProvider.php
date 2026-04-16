<?php

declare(strict_types=1);

namespace App\Providers;

use App\EventForm\Rules\Rule;
use App\EventForm\Rules\RuleRegistry;
use App\EventForm\Rules\RulesEngine;
use Illuminate\Support\ServiceProvider;

/**
 * Registreert de RulesEngine met alle gegenereerde Rule-klassen uit
 * `App\EventForm\Rules\RuleRegistry::all()`.
 *
 * RuleRegistry wordt door `transpile:event-form` (her)geschreven en bevat
 * expliciete `::class`-references — dat houdt static-analysis (PhpStorm,
 * PHPStan) blij én maakt de boot-volgorde van rules deterministisch.
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
        // Voor een fresh project waar `transpile:event-form` nog niet is gedraaid
        // bestaat RuleRegistry nog niet. In dat geval starten we met een lege
        // RulesEngine i.p.v. een hard fail.
        if (! class_exists(RuleRegistry::class)) {
            return [];
        }

        $rules = [];
        foreach (RuleRegistry::all() as $fqcn) {
            $rules[] = $this->app->make($fqcn);
        }

        return $rules;
    }
}
