<?php

namespace App\Filament\Admin\Resources\StatusResultaatColors\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StatusResultaatColorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status_name')
                    ->label(__('admin/resources/status_resultaat_color.columns.status_name.label'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('resultaat')
                    ->label(__('admin/resources/status_resultaat_color.columns.resultaat.label'))
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                ColorColumn::make('color')
                    ->label(__('admin/resources/status_resultaat_color.columns.color.label')),
            ])
            ->defaultSort('status_name')
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
