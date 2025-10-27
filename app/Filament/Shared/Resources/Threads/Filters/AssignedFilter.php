<?php

namespace App\Filament\Shared\Resources\Threads\Filters;

use Filament\Forms\Components\ToggleButtons;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class AssignedFilter
{
    public static function make()
    {
        return Filter::make('assigned')
            ->schema([
                ToggleButtons::make('assigned')
                    ->default('unassigned')
                    ->label(__('resources/thread.filters.assigned.label'))
                    ->grouped()
                    ->options([
                        'unassigned' => __('resources/thread.filters.assigned.options.unassigned'),
                        'self' => __('resources/thread.filters.assigned.options.self'),
                        'all' => __('resources/thread.filters.assigned.options.all'),
                    ]),
            ])
            ->query(function (Builder $query, array $data): Builder {
                return $query
                    ->when(
                        $data['assigned'] === 'unassigned',
                        fn (Builder $query, $date): Builder => $query->whereDoesntHave('assignedUsers'),
                    )
                    ->when(
                        $data['assigned'] === 'self',
                        fn (Builder $query, $date): Builder => $query->whereHas('assignedUsers', fn (Builder $q) => $q->where('id', auth()->id())),
                    );
            });
    }
}
