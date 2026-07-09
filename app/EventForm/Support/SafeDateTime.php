<?php

declare(strict_types=1);

namespace App\EventForm\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Null-safe helpers for the datetime values that flow through the event form.
 *
 * A native `datetime-local` input lets a user type a year with more than four
 * digits (e.g. `20256-09-20T16:00`). Carbon then throws
 * `InvalidFormatException` ("Double time specification"), which surfaced as a
 * hard 500 during Livewire updates. These helpers turn such values into `null`
 * instead of throwing.
 */
final class SafeDateTime
{
    /**
     * A clean ISO date or datetime with a four-digit year.
     */
    private const VALID_PATTERN = '/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?$/';

    /**
     * A datetime-shaped string whose year has five or more digits. This is the
     * malformed shape we heal; it is specific enough not to touch UUIDs, free
     * text or valid four-digit dates.
     */
    private const OVERLONG_YEAR_PATTERN = '/^\d{5,}-\d{1,2}-\d{1,2}/';

    /**
     * Parse a value into a Carbon instance, or return null when it is blank or
     * cannot be parsed cleanly.
     */
    public static function parse(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if (! is_string($value) || ! preg_match(self::VALID_PATTERN, $value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Recursively null out any datetime string with an over-long (5+ digit)
     * year. Non-datetime values are left untouched, so this is safe to run over
     * a whole form-state snapshot (including nested repeater arrays).
     *
     * @param  array<array-key, mixed>  $state
     * @return array<array-key, mixed>
     */
    public static function sanitizeState(array $state): array
    {
        foreach ($state as $key => $value) {
            if (is_array($value)) {
                $state[$key] = self::sanitizeState($value);
            } elseif (is_string($value) && preg_match(self::OVERLONG_YEAR_PATTERN, $value)) {
                $state[$key] = null;
            }
        }

        return $state;
    }
}
