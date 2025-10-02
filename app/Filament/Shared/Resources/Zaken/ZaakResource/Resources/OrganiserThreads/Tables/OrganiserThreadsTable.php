<?php

namespace App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\OrganiserThreads\Tables;

use App\Filament\Shared\Resources\Threads\Tables\Components\UnreadMessagesColumn;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrganiserThreadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modelLabel(__('resources/organiser_thread.label'))
            ->pluralModelLabel(__('resources/organiser_thread.plural_label'))
            ->columns([
                TextColumn::make('title')
                    ->label(__('resources/organiser_thread.columns.title.label'))
                    ->searchable(),
                TextColumn::make('createdBy.name')
                    ->label(__('resources/organiser_thread.columns.created_by.label'))
                    ->sortable(),
                UnreadMessagesColumn::make(),
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
