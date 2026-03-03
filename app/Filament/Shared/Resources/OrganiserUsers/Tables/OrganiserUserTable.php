<?php

namespace App\Filament\Shared\Resources\OrganiserUsers\Tables;

use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\Users\OrganiserUser;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class OrganiserUserTable
{
    public static function configure(Table $table, Organisation $organisation): Table
    {
        return $table
            ->modelLabel(__('resources/organiser_user.label'))
            ->pluralModelLabel(__('resources/organiser_user.plural_label'))
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('organiser/resources/user.columns.name.label'))
                    ->description(fn (OrganiserUser $record): string => $record->email)
                    ->searchable(),
                SelectColumn::make('pivot.role')
                    ->label(__('organiser/resources/user.columns.role.label'))
                    ->options(OrganisationRole::class)
                    ->selectablePlaceholder(false)
                    ->updateStateUsing(function (OrganiserUser $record, string $state) use ($organisation): void {
                        $record->organisations()->updateExistingPivot($organisation->id, ['role' => $state]);
                    })
                    ->afterStateUpdated(function () {
                        Notification::make()
                            ->title(__('organiser/resources/user.columns.role.notification'))
                            ->success()
                            ->send();
                    })
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
                TrashedFilter::make(),
            ])
            ->recordActions([
                RestoreAction::make(),
                EditAction::make(),
                DetachAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
