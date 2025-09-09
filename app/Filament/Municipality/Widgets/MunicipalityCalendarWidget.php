<?php

namespace App\Filament\Municipality\Widgets;

use App\Filament\Shared\Resources\Zaken\Schemas\Components\RisicoClassificatiesSelect;
use App\Filament\Shared\Widgets\CalendarWidget;
use App\Models\Organisation;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Database\Eloquent\Builder;

class MunicipalityCalendarWidget extends CalendarWidget
{
    protected function getFilterSchema(): array
    {
        return [
            Select::make('organisations')
                ->label(__('admin/resources/organisation.plural_label'))
                ->options(fn () => Organisation::query()->orderBy('name')->pluck('name', 'id'))
                ->multiple()
                ->searchable()
                ->preload(),
            RisicoClassificatiesSelect::make(),
        ];
    }

    protected function applyContextFilters(Builder $query, FetchInfo $info): Builder
    {
        /** @var \App\Models\Municipality $municipality */
        $municipality = Filament::getTenant();

        $query->whereHas('zaaktype', fn (Builder $q) => $q->where('municipality_id', $municipality->id));

        $filters = $this->filters ?? [];

        if (! empty($filters['organisations'])) {
            $query->whereIn('organisation_id', $filters['organisations']);
        }

        if (! empty($filters['risico_classificaties'])) {
            $query->whereIn('reference_data->risico_classificatie', $filters['risico_classificaties']);
        }

        return $query;
    }
}
