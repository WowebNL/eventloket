<?php

namespace App\Filament\Shared\Resources\Activities\Tables;

use App\Models\Message;
use App\Models\Threads\AdviceThread;
use App\Models\Threads\OrganiserThread;
use App\Models\Zaak;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modelLabel(__('resources/activity.label'))
            ->pluralModelLabel(__('resources/activity.plural_label'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('log_name')
                    ->label(__('resources/activity.columns.log_name.label'))
                    ->formatStateUsing(fn ($state) => __("activity/log_name.$state"))
                    ->badge()
                    ->searchable(),
                TextColumn::make('event')
                    ->label(__('resources/activity.columns.event.label'))
                    ->formatStateUsing(fn ($state) => __("activity/event.$state"))
                    ->badge()
                    ->searchable(),
                TextColumn::make('causer.name')
                    ->label(__('resources/activity.columns.causer.label'))
                    ->description(fn ($record) => $record->causer->role->getLabel())
                    ->searchable(),
                TextColumn::make('subject_type')
                    ->label(__('resources/activity.columns.subject.label'))
                    ->formatStateUsing(fn ($state) => match ($state) {
                        Zaak::class => __('resources/zaak.label'),
                        AdviceThread::class => __('resources/advice_thread.label'),
                        OrganiserThread::class => __('resources/organiser_thread.label'),
                        Message::class => __('resources/message.label'),
                        default => $state,
                    })
                    ->description(fn (Activity $record) => match ($record->subject_type) {
                        /** @phpstan-ignore-next-line */
                        Zaak::class => $record->subject?->reference_data->naam_evenement,
                        /** @phpstan-ignore-next-line */
                        AdviceThread::class => $record->subject?->title,
                        /** @phpstan-ignore-next-line */
                        OrganiserThread::class => $record->subject?->title,
                        /** @phpstan-ignore-next-line */
                        Message::class => $record->subject?->thread?->title,
                        default => null,
                    }),
                TextColumn::make('created_at')
                    ->label(__('resources/activity.columns.created_at.label'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
