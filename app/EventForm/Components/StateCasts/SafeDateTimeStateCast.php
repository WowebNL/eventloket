<?php

declare(strict_types=1);

namespace App\EventForm\Components\StateCasts;

use Filament\Schemas\Components\StateCasts\DateTimeStateCast;

/**
 * Null-safe variant of Filament's {@see DateTimeStateCast}.
 *
 * The parent `get()` runs an unguarded `Carbon::parse()` on the raw state,
 * which throws on a malformed value (e.g. a year with too many digits) and
 * surfaces as a 500 during a Livewire update. Here we return `null` instead so
 * an invalid value renders as an empty field rather than crashing the page.
 */
class SafeDateTimeStateCast extends DateTimeStateCast
{
    public function get(mixed $state): ?string
    {
        try {
            return parent::get($state);
        } catch (\Throwable) {
            return null;
        }
    }
}
