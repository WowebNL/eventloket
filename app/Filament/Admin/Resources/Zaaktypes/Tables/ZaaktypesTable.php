<?php

namespace App\Filament\Admin\Resources\Zaaktypes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ZaaktypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin/resources/zaaktype.columns.name.label'))
                    ->searchable(),
                TextColumn::make('municipality.name')
                    ->label(__('admin/resources/zaaktype.columns.municipality.label'))
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label(__('admin/resources/zaaktype.columns.is_active.label'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('admin/resources/zaaktype.columns.created_at.label'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('admin/resources/zaaktype.columns.updated_at.label'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
