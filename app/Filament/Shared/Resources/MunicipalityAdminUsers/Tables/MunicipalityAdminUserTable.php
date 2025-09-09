<?php

namespace App\Filament\Shared\Resources\MunicipalityAdminUsers\Tables;

use App\Enums\Role;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MunicipalityAdminUserTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modelLabel(__('resources/municipality_admin_user.label'))
            ->pluralModelLabel(__('resources/municipality_admin_user.plural_label'))
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('resources/municipality_admin_user.columns.name.label'))
                    ->description(fn (User $record): string => $record->email)
                    ->searchable(),
                SelectColumn::make('role')
                    ->label(__('resources/municipality_admin_user.columns.role.label'))
                    ->options([
                        Role::Reviewer->value => Role::Reviewer->getLabel(),
                        Role::MunicipalityAdmin->value => Role::MunicipalityAdmin->getLabel(),
                        Role::ReviewerMunicipalityAdmin->value => Role::ReviewerMunicipalityAdmin->getLabel(),
                    ])
                    ->selectablePlaceholder(false)
                    ->afterStateUpdated(function () {
                        Notification::make()
                            ->title(__('resources/municipality_admin.columns.role.notification'))
                            ->success()
                            ->send();
                    }),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
