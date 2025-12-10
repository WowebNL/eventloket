<?php

namespace App\Filament\Shared\Resources\AdvisorUsers\Tables;

use App\Enums\AdvisoryRole;
use App\Models\Advisory;
use App\Models\Users\AdvisorUser;
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

class AdvisorUserTable
{
    public static function configure(Table $table, Advisory $advisory): Table
    {
        return $table
            ->modelLabel(__('resources/advisor_user.label'))
            ->pluralModelLabel(__('resources/advisor_user.plural_label'))
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('resources/advisor_user.columns.name.label'))
                    ->description(fn (AdvisorUser $record): string => $record->email)
                    ->searchable(),
                SelectColumn::make('pivot.role')
                    ->label(__('resources/advisor_user.columns.role.label'))
                    ->options(AdvisoryRole::class)
                    ->selectablePlaceholder(false)
                    /** @phpstan-ignore-next-line */
                    ->getStateUsing(fn (AdvisorUser $record) => $record->advisories()->wherePivot('advisory_id', $advisory->id)->first()?->pivot->role)
                    ->updateStateUsing(function (AdvisorUser $record, string $state) use ($advisory): void {
                        $record->advisories()->updateExistingPivot($advisory->id, ['role' => $state]);
                    })
                    ->afterStateUpdated(function () {
                        Notification::make()
                            ->title(__('resources/advisor_user.columns.role.notification'))
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
                DetachAction::make()->visible(fn (AdvisorUser $record) => $record->advisories()->count() > 1),
                DeleteAction::make()->visible(fn (AdvisorUser $record) => $record->id !== auth()->id()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
