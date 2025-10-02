<?php

namespace App\Filament\Shared\Resources\Threads\Tables\Components;

use App\Models\Thread;
use Filament\Tables\Columns\TextColumn;

class LatestMessageColumn
{
    public static function make()
    {
        return TextColumn::make('latest_message')
            ->label(__('resources/thread.columns.latest_message.label'))
            /** @phpstan-ignore-next-line */
            ->getStateUsing(fn (Thread $record) => strip_tags($record->messages->last()?->body))
            ->limit(50)
            /** @phpstan-ignore-next-line */
            ->description(fn (Thread $record) => $record->messages->last()?->created_at->diffForHumans());
    }
}
