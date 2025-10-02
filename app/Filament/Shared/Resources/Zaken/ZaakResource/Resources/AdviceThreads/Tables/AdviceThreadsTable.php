<?php

namespace App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\Tables;

use App\Filament\Shared\Resources\Threads\Tables\Components\UnreadMessagesColumn;
use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\Filters\AdviceStatusFilter;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdviceThreadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modelLabel(__('resources/advice_thread.label'))
            ->pluralModelLabel(__('resources/advice_thread.plural_label'))
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
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
                TextColumn::make('createdBy.name')
                    ->label(__('resources/advice_thread.columns.created_by.label'))
                    ->sortable(),
                UnreadMessagesColumn::make(),
            ])
            ->defaultSort('created_at', direction: 'desc')
            ->filters([
                AdviceStatusFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
