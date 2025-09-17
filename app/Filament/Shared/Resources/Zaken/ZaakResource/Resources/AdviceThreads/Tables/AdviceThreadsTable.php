<?php

namespace App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdviceThreadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('type')
                    ->searchable(),
                TextColumn::make('advisory.name')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('advice_status')
                    ->searchable(),
                TextColumn::make('due_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('final_advice_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('createdBy.name')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
