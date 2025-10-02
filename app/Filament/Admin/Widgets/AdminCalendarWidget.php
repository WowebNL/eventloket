<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Shared\Resources\Zaken\Schemas\Components\RisicoClassificatiesSelect;
use App\Filament\Shared\Widgets\CalendarWidget;

class AdminCalendarWidget extends CalendarWidget
{
    protected function getFilterSchema(): array
    {
        return [
            $this->municipalitiesFilter(),
            $this->zaaktypesFilter(),
            $this->statusNameFilter(),
            $this->organisationsFilter(),
            RisicoClassificatiesSelect::make(),
            $this->searchFilter(),
        ];
    }
}
