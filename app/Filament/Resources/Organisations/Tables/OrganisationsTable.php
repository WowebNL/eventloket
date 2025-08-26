<?php

namespace App\Filament\Resources\Organisations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrganisationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin/resources/organisation.columns.name.label'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('coc_number')
                    ->label(__('admin/resources/organisation.columns.coc_number.label'))
                    ->searchable(),
                TextColumn::make('address')
                    ->label(__('admin/resources/organisation.columns.address.label'))
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('admin/resources/organisation.columns.email.label'))
                    ->searchable(),
                TextColumn::make('phone')
                    ->label(__('admin/resources/organisation.columns.phone.label'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
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
