<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;
use RuntimeException;

/**
 * Evalueert alle geregistreerde Rule-instanties op een FormState.
 *
 * Gedrag:
 *  - Alle rules worden in volgorde geëvalueerd.
 *  - Rules zijn cross-page: een wijziging op stap 1 mag stap 9 uitschakelen.
 *  - Eén evaluate-call voert maximaal `$maxPasses` passes uit, tot de state
 *    een fixpoint bereikt (geen veranderingen meer).
 *  - Als er na `$maxPasses` nog steeds wijzigingen optreden, gooien we een
 *    exception — oscillatie duidt op conflicterende rules.
 */
class RulesEngine
{
    /**
     * Cache van per-stap gefilterde rules. RulesEngine is in de
     * container een singleton (zie EventFormServiceProvider), dus deze
     * map blijft levenlang van het proces hangen — opnieuw filteren bij
     * elke keystroke kost onnodig 144 iteraties.
     *
     * @var array<string, list<Rule>>
     */
    private array $rulesByStep = [];

    /** @param  iterable<Rule>  $rules */
    public function __construct(
        private readonly iterable $rules,
        private readonly int $maxPasses = 5,
    ) {}

    public function evaluate(FormState $state): void
    {
        // Wipe rule-driven field-hidden overrides zodat velden die door een
        // niet-meer-triggerende rule op `hidden=false` stonden, terugvallen
        // op hun default (component.hidden + conditional).
        $state->resetFieldHiddenOverrides();
        $state->resetStepApplicable();
        $this->evaluateSet($state, $this->rules);
    }

    /**
     * Evalueer alleen rules waarvan `triggerStepUuids()` de gegeven step-UUID
     * bevat. Voor state-changes tijdens het invullen van stap N is dit
     * voldoende en veel sneller dan een globale pass.
     */
    public function evaluateForStep(FormState $state, string $stepUuid): void
    {
        $state->resetFieldHiddenOverrides();
        $state->resetStepApplicable();
        $this->evaluateSet($state, $this->scopedRulesFor($stepUuid));
    }

    /**
     * @return list<Rule>
     */
    private function scopedRulesFor(string $stepUuid): array
    {
        if (isset($this->rulesByStep[$stepUuid])) {
            return $this->rulesByStep[$stepUuid];
        }

        $scoped = [];
        foreach ($this->rules as $rule) {
            $triggers = $rule->triggerStepUuids();
            if ($triggers === [] || in_array($stepUuid, $triggers, true)) {
                // Geen scope-info → globaal nodig; of expliciet deze stap.
                $scoped[] = $rule;
            }
        }

        return $this->rulesByStep[$stepUuid] = $scoped;
    }

    /** @param  iterable<Rule>  $rules */
    private function evaluateSet(FormState $state, iterable $rules): void
    {
        $previous = $this->snapshot($state);

        for ($pass = 1; $pass <= $this->maxPasses; $pass++) {
            foreach ($rules as $rule) {
                if ($rule->applies($state)) {
                    $rule->apply($state);
                }
            }

            $current = $this->snapshot($state);
            if ($current === $previous) {
                return; // fixpoint bereikt
            }
            $previous = $current;
        }

        throw new RuntimeException(sprintf(
            'RulesEngine did not reach a fixpoint after %d passes — likely oscillating rules',
            $this->maxPasses,
        ));
    }

    /**
     * Snapshot voor fixpoint-detectie. Eerder een json_encode +
     * recursieve deepSort, maar dat was de duurste operatie per
     * keystroke (1-3× per `updated()`-call). Twee observaties laten ons
     * dat terugbrengen naar één `serialize()`-call:
     *
     *   1. Onze FormState-mutators (`setField`, `setVariable`,
     *      `setStepApplicable`, `setFieldHidden`) overschrijven
     *      bestaande keys in-place en appenden nieuwe — PHP-arrays
     *      behouden insertion-order, dus tussen twee passes blijft de
     *      key-volgorde stabiel.
     *   2. Voor `===`-vergelijking hoeft de output niet
     *      menselijk-leesbaar te zijn. `serialize()` is ~3× sneller
     *      dan `json_encode()` en honoreert PHP-array-order zonder
     *      sortering.
     */
    private function snapshot(FormState $state): string
    {
        return serialize($state->toSnapshot());
    }
}
