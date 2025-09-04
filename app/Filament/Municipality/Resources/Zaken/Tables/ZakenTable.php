<?php

namespace App\Filament\Municipality\Resources\Zaken\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ZakenTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_data.naam_evenement')
                    ->label(__('municipality/resources/zaak.columns.naam_evenement.label'))
                    ->sortable()
                    ->searchable()
                    ->forceSearchCaseInsensitive(),
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
                    ->searchable()
                    ->forceSearchCaseInsensitive(),
                TextColumn::make('reference_data.status_name')
                    ->label(__('municipality/resources/zaak.columns.status.label'))
                    ->sortable()
                    ->searchable()
                    ->forceSearchCaseInsensitive(),

            ])
            ->filters([
                SelectFilter::make('reference_data.status_name')
                    ->label(__('municipality/resources/zaak.columns.status.label'))
                    ->options([
                        'Ontvangen' => 'Ontvangen',
                    ])
                    ->multiple()
                    ->attribute('reference_data->status_name'),
                SelectFilter::make('reference_data.risico_classificatie')
                    ->label(__('municipality/resources/zaak.columns.risico_classificatie.label'))
                    ->options([
                        '0' => '0',
                        'A' => 'A',
                        'B' => 'B',
                        'C' => 'C',
                    ])
                    ->multiple()
                    ->attribute('reference_data->risico_classificatie'),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
            ])
            ->defaultSort('created_at', direction: 'desc')
            ->poll(); // default 10s
    }
}
