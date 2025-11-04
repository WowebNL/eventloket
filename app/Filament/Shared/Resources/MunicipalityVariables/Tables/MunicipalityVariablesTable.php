<?php

namespace App\Filament\Shared\Resources\MunicipalityVariables\Tables;

use App\Filament\Admin\Resources\MunicipalityVariables\Pages\ListMunicipalityVariables;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MunicipalityVariablesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('resources/municipality_variable.columns.name.label')),
                TextColumn::make('key')
                    ->label(__('resources/municipality_variable.columns.key.label')),
                TextColumn::make('type')
                    ->label(__('resources/municipality_variable.columns.type.label')),
                TextColumn::make('value')
                    ->label(__('resources/municipality_variable.columns.value.label'))
                    ->limit()
                    ->formatStateUsing(function ($state) {
                        if (is_bool($state)) {
                            return $state ? __('Ja') : __('Nee');
                        }

                        return $state;
                    }),
                IconColumn::make('is_default')
                    ->label(__('resources/municipality_variable.columns.is_default.label'))
                    ->hidden(fn ($livewire) => $livewire instanceof ListMunicipalityVariables),
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
