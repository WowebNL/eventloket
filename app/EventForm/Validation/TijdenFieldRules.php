<?php

declare(strict_types=1);

namespace App\EventForm\Validation;

use App\EventForm\Components\EventDagenRepeater;

/**
 * Cross-field date constraints on the Tijden step. No logic of our own — we
 * lean on Filament's `->after()/->before()/->afterOrEqual()/->beforeOrEqual()`,
 * which internally use the Laravel rules `after`/`before`/etc.
 * (vendor/filament/forms/src/Components/Concerns/CanBeValidated.php).
 *
 * The values are raw modifier statements that the transpiler emits unchanged
 * after the DateTimePicker. Keeping them in a constant puts the rules in one
 * place and stops them getting lost on a re-transpile.
 *
 * How the moments relate:
 *
 *     OpbouwStart ≤ OpbouwEind ≤ EvenementStart ≤ EvenementEind ≤ AfbouwStart ≤ AfbouwEind
 *
 * EvenementStart must also be today at the earliest, which stops an organiser
 * applying for an event in the past.
 *
 * These constraints bound the whole period. The rules for the per-day rows
 * inside such a period live on the repeater itself.
 *
 * @see EventDagenRepeater
 */
final class TijdenFieldRules
{
    /**
     * @var array<string, list<string>> Field key → list of modifier statements
     *                                  the transpiler emits after the base
     *                                  make(). Statements must carry no leading
     *                                  spaces; the transpiler handles indent.
     */
    public const PER_FIELD = [
        'EvenementStart' => [
            "->afterOrEqual('today')",
        ],
        'EvenementEind' => [
            "->afterOrEqual('EvenementStart')",
        ],
        'OpbouwEind' => [
            "->beforeOrEqual('EvenementStart')",
        ],
        'OpbouwStart' => [
            "->beforeOrEqual('OpbouwEind')",
        ],
        'AfbouwStart' => [
            "->afterOrEqual('EvenementEind')",
        ],
        'AfbouwEind' => [
            "->afterOrEqual('AfbouwStart')",
        ],
    ];
}
