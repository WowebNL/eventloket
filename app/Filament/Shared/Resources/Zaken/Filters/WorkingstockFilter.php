<?php

namespace App\Filament\Shared\Resources\Zaken\Filters;

use Filament\Forms\Components\ToggleButtons;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class WorkingstockFilter
{
    public static function make()
    {
        return Filter::make('workingstock')
            ->schema([
                ToggleButtons::make('workingstock')
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
                        $data['workingstock'] === 'new',
                        fn (Builder $query, $date): Builder => $query->whereNull(['handled_status_set_by_user_id', 'reference_data->resultaat']),
                    )
                    ->when(
                        $data['workingstock'] === 'me',
                        fn (Builder $query, $date): Builder => $query->where('handled_status_set_by_user_id', auth()->id())->whereNull('reference_data->resultaat'),
                    );
            });
    }
}
