<?php

namespace App\Filament\Shared\Resources\ReviewerUsers\Tables;

use App\Enums\Role;
use App\Models\Users\MunicipalityUser;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReviewerUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modelLabel(__('resources/reviewer_user.label'))
            ->pluralModelLabel(__('resources/reviewer_user.plural_label'))
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('resources/reviewer_user.columns.name.label'))
                    ->description(fn (MunicipalityUser $record): string => $record->email)
                    ->searchable(),
                SelectColumn::make('role')
                    ->label(__('municipality/resources/municipality_admin.columns.role.label'))
                    ->options([
                        Role::Reviewer->value => Role::Reviewer->getLabel(),
                        Role::ReviewerMunicipalityAdmin->value => Role::ReviewerMunicipalityAdmin->getLabel(),
                    ])
                    ->selectablePlaceholder(false)
                    ->afterStateUpdated(function () {
                        Notification::make()
                            ->title(__('municipality/resources/municipality_admin.columns.role.notification'))
                            ->success()
                            ->send();
                    })
                    ->disabled(fn (): bool => ! in_array(auth()->user()->role, [Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin, Role::Admin])),

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
