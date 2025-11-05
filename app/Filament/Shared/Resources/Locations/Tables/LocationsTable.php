<?php

namespace App\Filament\Shared\Resources\Locations\Tables;

use App\Models\Location;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LocationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modelLabel(__('resources/location.label'))
            ->pluralModelLabel(__('resources/location.plural_label'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('resources/location.columns.name.label'))
                    ->searchable(),
                TextColumn::make('address')
                    ->label(__('resources/location.columns.address.label'))
                    ->getStateUsing(function (Location $record) {
                        return "{$record->street_name} {$record->house_number}{$record->house_letter}{$record->house_number_addition}, {$record->postal_code} {$record->city_name}";
                    }),
                IconColumn::make('active')
                    ->label(__('resources/location.columns.active.label'))
                    ->boolean(),
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
