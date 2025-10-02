<?php

namespace App\Filament\Shared\Resources\Threads\Filters;

use Filament\Forms\Components\ToggleButtons;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class UnreadMessagesFilter
{
    public static function make()
    {
        return Filter::make('unread')
            ->schema([
                ToggleButtons::make('unread')
                    ->default('unread')
                    ->label(__('resources/thread.filters.unread_messages.label'))
                    ->grouped()
                    ->options([
                        'unread' => __('resources/thread.filters.unread_messages.options.unread'),
                        'all' => __('resources/thread.filters.unread_messages.options.all'),
                    ]),
            ])
            ->query(function (Builder $query, array $data): Builder {
                return $query
                    ->when(
                        $data['unread'] === 'unread',
                        fn (Builder $query, $date): Builder => $query->whereHas('unreadMessages'),
                    );
            });
    }
}
