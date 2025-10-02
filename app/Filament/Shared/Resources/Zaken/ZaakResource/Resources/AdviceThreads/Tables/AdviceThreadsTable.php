<?php

namespace App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\Tables;

use App\Enums\AdviceStatus;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AdviceThreadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('resources/advice_thread.columns.title.label'))
                    ->searchable(),
                TextColumn::make('advisory.name')
                    ->label(__('resources/advice_thread.columns.advisory.label'))
                    ->sortable(),
                TextColumn::make('advice_status')
                    ->label(__('resources/advice_thread.columns.advice_status.label'))
                    ->badge()
                    ->searchable(),
                TextColumn::make('advice_due_at')
                    ->label(__('resources/advice_thread.columns.advice_due_at.label'))
                    ->dateTime(config('app.datetime_format'))
                    ->sortable(),
                TextColumn::make('createdBy.name')
                    ->label(__('resources/advice_thread.columns.created_by.label'))
                    ->sortable(),
                TextColumn::make('unread_messages_count')
                    ->label(__('resources/advice_thread.columns.unread_messages_count.label'))
                    ->counts('unreadMessages')
                    ->badge()
                    ->color(fn ($state) => $state ? 'primary' : 'gray')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('advice_status')
                    ->label(__('resources/advice_thread.columns.advice_status.label'))
                    ->options(AdviceStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
