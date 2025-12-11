<?php

namespace App\Filament\Shared\Resources\Zaken\Tables;

use App\Enums\Role;
use App\Filament\Shared\Resources\Zaken\Filters\AdvisorWorkingstockFilter;
use App\Filament\Shared\Resources\Zaken\Filters\WorkingstockFilter;
use App\Models\Zaak;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

class ZakenTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_data.naam_evenement')
                    ->label(__('resources/zaak.columns.naam_evenement.label'))
                    ->sortable()
                    ->searchable()
                    ->forceSearchCaseInsensitive()
                    ->toggleable(),
                TextColumn::make('reference_data.organisator')
                    ->label(__('municipality/resources/zaak.columns.organisator.label'))
                    ->sortable()
                    ->searchable()
                    ->forceSearchCaseInsensitive()
                    ->hidden(fn () => auth()->user()->role == Role::Organiser)
                    ->toggleable(),
                TextColumn::make('public_id')
                    ->label(__('resources/zaak.columns.public_id.label'))
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('zaaktype.name')
                    ->label(__('resources/zaak.columns.zaaktype.label'))
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('reference_data.registratiedatum')
                    ->dateTime(config('app.date_format'))
                    ->label(__('resources/zaak.columns.registratiedatum.label'))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('handledStatusSetByUser.name')
                    ->label(__('resources/zaak.columns.handled_status_set_by_user.label'))
                    ->sortable()
                    ->toggleable()
                    ->searchable()
                    ->forceSearchCaseInsensitive(),
                TextColumn::make('reference_data.risico_classificatie')
                    ->label(__('resources/zaak.columns.risico_classificatie.label'))
                    ->sortable()
                    ->toggleable()
                    ->searchable()
                    ->forceSearchCaseInsensitive(),
                TextColumn::make('reference_data.status_name')
                    ->label(__('resources/zaak.columns.status.label'))
                    ->sortable()
                    ->toggleable()
                    ->searchable()
                    ->forceSearchCaseInsensitive(),
                TextColumn::make('reference_data.start_evenement')
                    ->label(__('resources/zaak.columns.start_evenement.label'))
                    ->dateTime(config('app.datetime_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable()
                    ->forceSearchCaseInsensitive(),
                TextColumn::make('reference_data.eind_evenement')
                    ->label(__('resources/zaak.columns.eind_evenement.label'))
                    ->dateTime(config('app.datetime_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable()
                    ->forceSearchCaseInsensitive(),
                TextColumn::make('reference_data.naam_locatie_evenement')
                    ->label(__('resources/zaak.columns.naam_locatie_evenement.label'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable()
                    ->forceSearchCaseInsensitive(),
                TextColumn::make('reference_data.types_evenement')
                    ->label(__('resources/zaak.columns.types_evenement.label'))
                    ->badge()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable()
                    ->forceSearchCaseInsensitive(),
                TextColumn::make('reference_data.resultaat')
                    ->label(__('resources/zaak.columns.resultaat.label'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable()
                    ->forceSearchCaseInsensitive(),
                TextColumn::make('reference_data.aanwezigen')
                    ->label(__('resources/zaak.columns.aanwezigen.label'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable()
                    ->forceSearchCaseInsensitive(),
            ])
            ->reorderableColumns()
            ->filters([
                WorkingstockFilter::make()
                    ->columnSpan(2)
                    ->visible(fn () => in_array(auth()->user()->role, [Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin, Role::Reviewer])),
                AdvisorWorkingstockFilter::make()
                    ->columnSpan(2)
                    ->visible(fn () => auth()->user()->role === Role::Advisor),
                SelectFilter::make('reference_data.status_name')
                    ->label(__('resources/zaak.columns.status.label'))
                    ->options(function () {
                        return Cache::remember('zaak_status_name_options', 60 * 60 * 24, function () {
                            return Zaak::all()
                                ->pluck('reference_data.status_name')
                                ->unique()
                                ->sort()
                                ->mapWithKeys(fn ($status_name) => [$status_name => $status_name]);
                        });
                    })
                    ->multiple()
                    ->attribute('reference_data->status_name'),
                SelectFilter::make('reference_data.risico_classificatie')
                    ->label(__('resources/zaak.columns.risico_classificatie.label'))
                    ->options([
                        '0' => '0',
                        'A' => 'A',
                        'B' => 'B',
                        'C' => 'C',
                    ])
                    ->multiple()
                    ->attribute('reference_data->risico_classificatie'),
                SelectFilter::make('organisation_id')
                    ->label(__('municipality/resources/zaak.columns.organisation.label'))
                    ->relationship('organisation', 'name')
                    ->searchable()
                    ->multiple()
                    ->hidden(fn () => auth()->user()->role == Role::Organiser),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
            ])
            ->defaultSort('created_at', direction: 'desc')
            ->poll(); // default 10s
    }
}
