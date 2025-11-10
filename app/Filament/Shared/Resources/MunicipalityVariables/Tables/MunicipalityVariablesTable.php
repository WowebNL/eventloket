<?php

namespace App\Filament\Shared\Resources\MunicipalityVariables\Tables;

use App\Enums\MunicipalityVariableType;
use App\Filament\Admin\Resources\MunicipalityVariables\Pages\ListMunicipalityVariables;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MunicipalityVariablesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modelLabel(__('resources/municipality_variable.label'))
            ->pluralModelLabel(__('resources/municipality_variable.plural_label'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('resources/municipality_variable.columns.name.label'))
                    ->searchable(),
                TextColumn::make('key')
                    ->label(__('resources/municipality_variable.columns.key.label'))
                    ->searchable(),
                TextColumn::make('type')
                    ->label(__('resources/municipality_variable.columns.type.label'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('formatted_filament_table_value')
                    ->label(__('resources/municipality_variable.columns.value.label'))
                    ->limit(),
                IconColumn::make('is_default')
                    ->label(__('resources/municipality_variable.columns.is_default.label'))
                    ->hidden(fn ($livewire) => $livewire instanceof ListMunicipalityVariables),
                TextColumn::make('created_at')
                    ->label(__('resources/municipality_variable.columns.created_at.label'))
                    ->dateTime(config('app.datetime_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('resources/municipality_variable.columns.updated_at.label'))
                    ->dateTime(config('app.datetime_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->searchable(['value'])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('resources/municipality_variable.columns.type.label'))
                    ->options(MunicipalityVariableType::class),
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
