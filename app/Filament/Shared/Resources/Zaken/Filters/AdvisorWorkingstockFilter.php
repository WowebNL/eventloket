<?php

namespace App\Filament\Shared\Resources\Zaken\Filters;

use Filament\Facades\Filament;
use Filament\Forms\Components\ToggleButtons;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class AdvisorWorkingstockFilter
{
    public static function make()
    {
        return Filter::make('workingstock-advisor')
            ->schema([
                ToggleButtons::make('workingstock-adv')
                    ->default('new')
                    ->label(__('resources/zaak.filters.workingstock.label'))
                    ->grouped()
                    ->options([
                        'new' => __('resources/zaak.filters.workingstock.options.new'),
                        'me' => __('resources/zaak.filters.workingstock.options.me'),
                        'all' => __('resources/zaak.filters.workingstock.options.all'),
                    ]),
            ])
            ->query(function (Builder $query, array $data): Builder {
                return $query
                    ->when(
                        isset($data['workingstock-adv']) && $data['workingstock-adv'] === 'new',
                        fn (Builder $query, $date): Builder => $query
                            ->whereHas('adviceThreads', fn (Builder $query) => $query->whereDoesntHave('assignedUsers')),
                    )
                    ->when(
                        isset($data['workingstock-adv']) && $data['workingstock-adv'] === 'me',
                        fn (Builder $query, $date): Builder => $query
                            ->whereHas('adviceThreads.assignedUsers', fn (Builder $query) => $query->where('user_id', auth()->id())),
                    )
                    ->when(
                        isset($data['workingstock-adv']) && $data['workingstock-adv'] === 'all',
                        fn (Builder $query, $date): Builder => $query
                            ->whereHas('adviceThreads', fn (Builder $query) => $query->where('advisory_id', Filament::getTenant()->id)), // @phpstan-ignore-line
                    );
            });
    }
}
