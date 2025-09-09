<?php

namespace App\Filament\Organiser\Widgets;

use App\Filament\Shared\Resources\Zaken\Schemas\Components\RisicoClassificatiesSelect;
use App\Filament\Shared\Widgets\CalendarWidget;
use App\Models\Municipality;
use Filament\Forms\Components\Select;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Database\Eloquent\Builder;

class OrganiserCalendarWidget extends CalendarWidget
{
    protected function getFilterSchema(): array
    {
        return [
            Select::make('municipalities')
                ->label(__('admin/resources/municipality.plural_label'))
                ->options(fn () => Municipality::query()->orderBy('name')->pluck('name', 'id'))
                ->multiple()
                ->searchable()
                ->preload(),
            RisicoClassificatiesSelect::make(),
        ];
    }

    protected function applyContextFilters(Builder $query, FetchInfo $info): Builder
    {
        // TODO Lorenso: Filter toevoegen voor alleen goed gekeurde zaken.

        $filters = $this->filters ?? [];

        if (! empty($filters['municipalities'])) {
            $query->whereHas('zaaktype', fn (Builder $q) => $q->whereIn('municipality_id', $filters['municipalities']));
        }

        if (! empty($filters['risico_classificaties'])) {
            $query->whereIn('reference_data->risico_classificatie', $filters['risico_classificaties']);
        }

        return $query;
    }
}
