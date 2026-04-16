<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * Contract voor één gedragsregel in het evenementformulier.
 *
 * Rules zijn idempotent — `apply()` wordt soms meerdere keren per cyclus
 * aangeroepen (bij fixpoint-iteratie) en moet dus dezelfde uitkomst leveren.
 */
interface Rule
{
    /**
     * Identificeert de rule voor debugging + ordering. Gebruikt in de RulesEngine
     * om oscillaties te detecteren en te loggen.
     */
    public function identifier(): string;

    /**
     * Moet de rule nu worden toegepast? Lees alleen uit `$state`, geen side-effects.
     */
    public function applies(FormState $state): bool;

    /**
     * Pas de rule toe op `$state`. Mag alleen schrijven naar FormState; geen
     * HTTP-calls, geen DB-mutaties. Voor service-fetches bestaat
     * `ServiceFetchRule` (aparte afhandeling in de engine).
     */
    public function apply(FormState $state): void;
}
