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
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

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
                    }),
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
                DetachAction::make()
                    ->visible(fn (AdvisorUser $record) => self::getDetachRestriction($record, $advisory) === null)
                    // This table is also used on a plain resource list page (advisor panel), where the
                    // table has no relationship and Filament's default detach handler would fail on
                    // a null relationship. Detaching from the advisory the table was configured with
                    // is equivalent in the relation manager, which uses that same relationship.
                    ->using(fn (AdvisorUser $record) => $advisory->users()->detach($record->getKey())),
                DeleteAction::make()->visible(fn (AdvisorUser $record) => $record->id !== auth()->id()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->using(function (DetachBulkAction $action, EloquentCollection|Collection|LazyCollection $records) use ($advisory): void {
                            $detachableKeys = [];
                            $detachedAdminCount = 0;

                            foreach ($records as $record) {
                                /** @var AdvisorUser $record */
                                $restriction = self::getDetachRestriction($record, $advisory, $detachedAdminCount);

                                if ($restriction === null) {
                                    $detachableKeys[] = $record->getKey();

                                    if (self::isAdminOf($record, $advisory)) {
                                        $detachedAdminCount++;
                                    }

                                    continue;
                                }

                                // Same guards as the single action. Skip those records instead of
                                // failing the whole batch, and let Filament report how many were skipped.
                                $action->reportBulkProcessingFailure(
                                    $restriction,
                                    fn (int $count): string => trans_choice("resources/advisor_user.actions.detach.skipped_{$restriction}", $count),
                                );
                            }

                            if ($detachableKeys !== []) {
                                $advisory->users()->detach($detachableKeys);
                            }
                        })
                        // Filament has no default title for a partially failed bulk action, and without
                        // one the notification is never sent. Give the skipped records a voice.
                        ->failureNotificationTitle(fn (int $successCount): string => $successCount > 0
                            ? __('resources/advisor_user.actions.detach.notifications.partially_detached.title')
                            : __('resources/advisor_user.actions.detach.notifications.none_detached.title')),
                    DeleteBulkAction::make()
                        // Authorize every selected record against the same policy as the single
                        // delete action, and never include the acting user, which that action hides.
                        ->authorizeIndividualRecords(function (AdvisorUser $record): bool {
                            $user = auth()->user();

                            return $user !== null && $record->isNot($user) && $user->can('delete', $record);
                        }),
                ]),
            ]);
    }

    /**
     * Returns why a user may not be detached from the advisory, or null when they may.
     *
     * A user may not detach themselves, may only be detached while they still belong to
     * another advisory, and the last admin of an advisory may not be detached. The bulk
     * action passes the number of admins it already detaches, so a single batch cannot
     * remove every admin either.
     */
    private static function getDetachRestriction(AdvisorUser $record, Advisory $advisory, int $detachedAdminCount = 0): ?string
    {
        if ($record->is(auth()->user())) {
            return 'self';
        }

        if ($record->advisories()->count() <= 1) {
            return 'last_advisory';
        }

        if (self::isAdminOf($record, $advisory) && $advisory->adminUsers()->count() - $detachedAdminCount <= 1) {
            return 'last_admin';
        }

        return null;
    }

    private static function isAdminOf(AdvisorUser $record, Advisory $advisory): bool
    {
        return $record->canAccessAdvisory($advisory->id, as: AdvisoryRole::Admin);
    }
}
