<?php

namespace App\Filament\Shared\Resources\Threads\Tables\Components;

use Filament\Tables\Columns\TextColumn;

class UnreadMessagesColumn
{
    public static function make()
    {
        return TextColumn::make('unread_messages_count')
            ->label(__('resources/thread.columns.unread_messages_count.label'))
            ->counts('unreadMessages')
            ->badge()
            ->color(fn ($state) => $state ? 'primary' : 'gray')
            ->sortable();
    }
}
