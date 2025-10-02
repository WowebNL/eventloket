<?php

namespace App\Filament\Advisor\Widgets;

use App\Filament\Shared\Resources\Zaken\Schemas\Components\RisicoClassificatiesSelect;
use App\Filament\Shared\Widgets\CalendarWidget;

class AdvisorCalendarWidget extends CalendarWidget
{
    protected function getFilterSchema(): array
    {
        return [
            $this->municipalitiesFilter(),
            $this->statusNameFilter(),
            $this->organisationsFilter(),
            RisicoClassificatiesSelect::make(),
            $this->searchFilter(),
        ];
    }
}
