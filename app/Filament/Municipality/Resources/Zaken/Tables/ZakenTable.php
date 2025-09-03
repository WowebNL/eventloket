<?php

namespace App\Filament\Municipality\Resources\Zaken\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ZakenTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_id')
                    ->label(__('municipality/resources/zaak.columns.public_id.label'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('zaaktype.name')
                    ->label(__('municipality/resources/zaak.columns.zaaktype.label'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('reference_data.registratiedatum')
                    ->dateTime('d-m-Y')
                    ->label(__('municipality/resources/zaak.columns.registratiedatum.label'))
                    ->sortable(),
                TextColumn::make('reference_data.risico_classificatie')
                    ->label(__('municipality/resources/zaak.columns.risico_classificatie.label'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('reference_data.status_name')
                    ->label(__('municipality/resources/zaak.columns.status.label'))
                    ->sortable()
                    ->searchable(),

            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
            ]);
    }
}
