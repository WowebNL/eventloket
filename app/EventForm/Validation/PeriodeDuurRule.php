<?php

declare(strict_types=1);

namespace App\EventForm\Validation;

use App\EventForm\Support\EventDagen;
use Closure;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Caps how long one period on the Tijden step may run.
 *
 * A datetime input accepts any date the browser lets through, including a year
 * far outside the range this form is meant to collect. EventDagen already
 * bounds its own day list, but a bound on its own would clip the period without
 * saying so. This rule is what turns the cap into a message the organiser can
 * act on, so the period is either fully expressed in day rows or refused.
 *
 * @see EventDagen::MAX_DAGEN for the bound itself
 */
final class PeriodeDuurRule
{
    /**
     * A rule for the end field of a period, measured against the start field
     * that opens the same period.
     */
    public static function voor(string $startKey): Closure
    {
        return static fn (Get $get): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get, $startKey): void {
            if (! EventDagen::overschrijdtMaximum($get($startKey), $value)) {
                return;
            }

            $fail(sprintf(
                'Een periode mag maximaal %d dagen beslaan. Controleer de startdatum en de einddatum.',
                EventDagen::MAX_DAGEN,
            ));
        };
    }
}
