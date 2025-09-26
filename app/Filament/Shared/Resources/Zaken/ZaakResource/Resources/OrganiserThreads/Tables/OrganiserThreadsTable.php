<?php

namespace App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\OrganiserThreads\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrganiserThreadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('resources/organiser_thread.columns.title.label'))
                    ->searchable(),
                TextColumn::make('createdBy.name')
                    ->label(__('resources/organiser_thread.columns.created_by.label'))
                    ->sortable(),
                TextColumn::make('unread_messages_count')
                    ->label(__('resources/organiser_thread.columns.unread_messages_count.label'))
                    ->counts('unreadMessages')
                    ->badge()
                    ->color(fn ($state) => $state ? 'primary' : 'gray')
                    ->sortable(),
            ])
            ->defaultSort('created_at', direction: 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
