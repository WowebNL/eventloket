<?php

namespace App\Filament\Municipality\Widgets;

use App\Filament\Shared\Resources\Zaken\Schemas\Components\RisicoClassificatiesSelect;
use App\Filament\Shared\Widgets\CalendarWidget;
use Filament\Facades\Filament;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Database\Eloquent\Builder;

class MunicipalityCalendarWidget extends CalendarWidget
{
    protected function getFilterSchema(): array
    {
        return [
            $this->zaaktypesFilter(),
            $this->statusNameFilter(),
            $this->organisationsFilter(),
            RisicoClassificatiesSelect::make(),
            $this->searchFilter(),
        ];
    }

    protected function applyContextFilters(Builder $query, ?FetchInfo $info = null): Builder
    {
        $query = parent::applyContextFilters($query, $info);

        /** @var \App\Models\Municipality $municipality */
        $municipality = Filament::getTenant();

        $query->whereHas('zaaktype', fn (Builder $q) => $q->where('municipality_id', $municipality->id));

        return $query;
    }
}
