<?php

declare(strict_types=1);

namespace App\Providers;

use App\EventForm\Rules\Rule;
use App\EventForm\Rules\RuleRegistry;
use App\EventForm\Rules\RulesEngine;
use Illuminate\Support\ServiceProvider;

/**
 * Registreert de RulesEngine met de rules uit
 * `App\EventForm\Rules\RuleRegistry::all()`. Sinds de pure-functionele
 * migratie is RuleRegistry handmatig onderhouden — wijzig 'm direct
 * wanneer een rule (de)gemigreerd wordt naar
 * FormDerivedState / FormFieldVisibility / FormStepApplicability /
 * FormSystemDerivedState.
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
        $rules = [];
        foreach (RuleRegistry::all() as $fqcn) {
            $rules[] = $this->app->make($fqcn);
        }

        return $rules;
    }
}
