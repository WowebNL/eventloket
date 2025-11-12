<?php

namespace App\Filament\Admin\Resources\Activities\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('log_name')
                    ->label(__('resources/activity.columns.log_name.label'))
                    ->formatStateUsing(fn ($state) => __("activity/log_name.$state"))
                    ->badge()
                    ->searchable(),
                TextColumn::make('event')
                    ->label(__('resources/activity.columns.event.label'))
                    ->formatStateUsing(fn ($state) => __("activity/event.$state"))
                    ->badge()
                    ->searchable(),
                TextColumn::make('causer.name')
                    ->label(__('resources/activity.columns.causer.label'))
                    ->description(fn ($record) => $record->causer->role->getLabel())
                    ->searchable(),
                TextColumn::make('subject_type')
                    ->label(__('resources/activity.columns.subject.label'))
                    ->description('Naam van de zaak'),
                TextColumn::make('created_at')
                    ->label(__('resources/activity.columns.created_at.label'))
                    ->dateTime()
                    ->sortable(),
            ])
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
