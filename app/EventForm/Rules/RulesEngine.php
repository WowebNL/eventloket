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
    /** @param  iterable<Rule>  $rules */
    public function __construct(
        private readonly iterable $rules,
        private readonly int $maxPasses = 5,
    ) {}

    public function evaluate(FormState $state): void
    {
        $this->evaluateSet($state, $this->rules);
    }

    /**
     * Evalueer alleen rules waarvan `triggerStepUuids()` de gegeven step-UUID
     * bevat. Voor state-changes tijdens het invullen van stap N is dit
     * voldoende en veel sneller dan een globale pass.
     */
    public function evaluateForStep(FormState $state, string $stepUuid): void
    {
        $scoped = [];
        foreach ($this->rules as $rule) {
            $triggers = $rule->triggerStepUuids();
            if ($triggers === [] || in_array($stepUuid, $triggers, true)) {
                // Geen scope-info → globaal nodig; of expliciet deze stap.
                $scoped[] = $rule;
            }
        }
        $this->evaluateSet($state, $scoped);
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
     * Stable serialisation van de state-aspects die rules kunnen beïnvloeden.
     * Gebruikt voor fixpoint-detectie.
     */
    private function snapshot(FormState $state): string
    {
        $snap = $state->toSnapshot();
        // Sort keys op alle niveaus zodat de vergelijking deterministisch is.
        $sorted = $this->deepSort($snap);

        return (string) json_encode($sorted, JSON_THROW_ON_ERROR);
    }

    private function deepSort(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if ($this->isList($value)) {
            return array_map(fn ($v) => $this->deepSort($v), $value);
        }

        ksort($value);
        foreach ($value as $k => $v) {
            $value[$k] = $this->deepSort($v);
        }

        return $value;
    }

    /** @param  array<int|string, mixed>  $arr */
    private function isList(array $arr): bool
    {
        if ($arr === []) {
            return true;
        }

        return array_keys($arr) === range(0, count($arr) - 1);
    }
}
