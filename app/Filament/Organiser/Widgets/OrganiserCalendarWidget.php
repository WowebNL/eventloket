<?php

namespace App\Filament\Organiser\Widgets;

use App\Filament\Shared\Resources\Zaken\Schemas\Components\RisicoClassificatiesSelect;
use App\Filament\Shared\Widgets\CalendarWidget;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Database\Eloquent\Builder;

class OrganiserCalendarWidget extends CalendarWidget
{
    protected function getFilterSchema(): array
    {
        return [
            $this->municipalitiesFilter(),
            $this->statusNameFilter(),
            RisicoClassificatiesSelect::make(),
            $this->searchFilter(),
        ];
    }

    protected function applyContextFilters(Builder $query, ?FetchInfo $info = null): Builder
    {
        $query = parent::applyContextFilters($query, $info);

        // TODO Lorenso: Filter toevoegen voor alleen goed gekeurde zaken.

        return $query;
    }
}
