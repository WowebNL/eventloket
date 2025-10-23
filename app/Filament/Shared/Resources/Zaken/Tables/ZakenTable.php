<?php

namespace App\Filament\Shared\Resources\Zaken\Tables;

use App\Enums\Role;
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
                    ->label(__('resources/zaak.columns.naam_evenement.label'))
                    ->sortable()
                    ->searchable()
                    ->forceSearchCaseInsensitive(),
                TextColumn::make('reference_data.organisator')
                    ->label(__('municipality/resources/zaak.columns.organisator.label'))
                    ->sortable()
                    ->searchable()
                    ->forceSearchCaseInsensitive()
                    ->hidden(fn () => auth()->user()->role == Role::Organiser),
                TextColumn::make('public_id')
                    ->label(__('resources/zaak.columns.public_id.label'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('zaaktype.name')
                    ->label(__('resources/zaak.columns.zaaktype.label'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('reference_data.registratiedatum')
                    ->dateTime(config('app.date_format'))
                    ->label(__('resources/zaak.columns.registratiedatum.label'))
                    ->sortable(),
                TextColumn::make('reference_data.risico_classificatie')
                    ->label(__('resources/zaak.columns.risico_classificatie.label'))
                    ->sortable()
                    ->searchable()
                    ->forceSearchCaseInsensitive(),
                TextColumn::make('reference_data.status_name')
                    ->label(__('resources/zaak.columns.status.label'))
                    ->sortable()
                    ->searchable()
                    ->forceSearchCaseInsensitive(),
            ])
            ->filters([
                SelectFilter::make('reference_data.status_name')
                    ->label(__('resources/zaak.columns.status.label'))
                    ->options([
                        'Ontvangen' => 'Ontvangen',
                    ])
                    ->multiple()
                    ->attribute('reference_data->status_name'),
                SelectFilter::make('reference_data.risico_classificatie')
                    ->label(__('resources/zaak.columns.risico_classificatie.label'))
                    ->options([
                        '0' => '0',
                        'A' => 'A',
                        'B' => 'B',
                        'C' => 'C',
                    ])
                    ->multiple()
                    ->attribute('reference_data->risico_classificatie'),
                SelectFilter::make('organisation_id')
                    ->label(__('municipality/resources/zaak.columns.organisation.label'))
                    ->relationship('organisation', 'name')
                    ->searchable()
                    ->multiple()
                    ->hidden(fn () => auth()->user()->role == Role::Organiser),
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
