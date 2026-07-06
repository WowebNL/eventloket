<?php

declare(strict_types=1);

namespace App\EventForm\Components;

use App\EventForm\Components\Concerns\HasSafeDateTimeStateCast;
use Filament\Forms\Components\DateTimePicker;

/**
 * DateTimePicker that never 500s on a malformed stored value.
 *
 * @see HasSafeDateTimeStateCast
 */
class EventDateTimePicker extends DateTimePicker
{
    use HasSafeDateTimeStateCast;
}
