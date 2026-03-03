<?php

namespace App\Filament\Municipality\Widgets;

use App\Filament\Shared\Resources\Zaken\Schemas\Components\RisicoClassificatiesSelect;
use App\Filament\Shared\Widgets\CalendarWidget;
use Filament\Facades\Filament;

class MunicipalityCalendarWidget extends CalendarWidget
{
    public function mount(): void
    {
        parent::mount();

        /** @var \App\Models\Municipality $municipality */
        $municipality = Filament::getTenant();

        $this->filters = [
            'municipalities' => [
                $municipality->id,
            ],
        ];
    }

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
