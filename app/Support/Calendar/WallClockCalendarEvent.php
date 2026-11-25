<?php

declare(strict_types=1);

namespace App\Support\Calendar;

use Filament\Support\Facades\FilamentTimezone;
use Guava\Calendar\ValueObjects\CalendarEvent;

/**
 * A calendar event that is sent to the browser as wall clock time.
 *
 * The calendar library converts a time that carries a UTC offset using the
 * browser's offset of today, so an event on the other side of a daylight
 * saving change would be shown an hour off. A time without an offset is shown
 * as is. With the Filament timezone in use, start and end are therefore sent
 * as the local time in that timezone, without an offset.
 */
class WallClockCalendarEvent extends CalendarEvent
{
    public function toCalendarObject(int $timezoneOffset, bool $useFilamentTimezone): array
    {
        $object = parent::toCalendarObject($timezoneOffset, $useFilamentTimezone);

        if (! $useFilamentTimezone) {
            return $object;
        }

        $timezone = $this->getTimezone() ?? FilamentTimezone::get();

        $object['start'] = $this->getStart()->copy()->setTimezone($timezone)->format('Y-m-d\TH:i:s');
        $object['end'] = $this->getEnd()->copy()->setTimezone($timezone)->format('Y-m-d\TH:i:s');

        return $object;
    }
}
