<?php

namespace App\Support;

use Carbon\Carbon;

class CarbonBusinessDaysMixin
{
    public function addBusinessDays()
    {
        return function (int $days): Carbon {
            /** @var Carbon $date */
            /** @phpstan-ignore-next-line */
            $date = $this->copy();

            while ($days > 0) {
                $date->addDay();

                if (! $date->isWeekend()) {
                    $days--;
                }
            }

            return $date;
        };
    }

    public function subBusinessDays()
    {
        return function (int $days): Carbon {
            /** @var Carbon $date */
            /** @phpstan-ignore-next-line */
            $date = $this->copy();

            while ($days > 0) {
                $date->subDay();

                if (! $date->isWeekend()) {
                    $days--;
                }
            }

            return $date;
        };
    }
}
