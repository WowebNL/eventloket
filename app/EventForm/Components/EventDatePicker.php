<?php

declare(strict_types=1);

namespace App\EventForm\Components;

use App\EventForm\Components\Concerns\HasSafeDateTimeStateCast;
use Filament\Forms\Components\DatePicker;

/**
 * DatePicker that never 500s on a malformed stored value.
 *
 * @see HasSafeDateTimeStateCast
 */
class EventDatePicker extends DatePicker
{
    use HasSafeDateTimeStateCast;
}
