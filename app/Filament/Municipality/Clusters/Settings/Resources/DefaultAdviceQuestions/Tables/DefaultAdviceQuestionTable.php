<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\DefaultAdviceQuestions\Tables;

use App\Models\Location;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DefaultAdviceQuestionTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modelLabel(__('resources/location.label'))
            ->pluralModelLabel(__('resources/location.plural_label'))
            ->columns([
                TextColumn::make('advisory.name')
                    ->label(__('municipality/resources/default-advice-question.columns.advisory.label'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('risico_classificatie')
                    ->label(__('municipality/resources/default-advice-question.columns.risico_classificatie.label'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('title')
                    ->label(__('municipality/resources/default-advice-question.columns.title.label'))
                    ->searchable()
                    ->limit(50),

                TextColumn::make('response_deadline_days')
                    ->label(__('municipality/resources/default-advice-question.columns.response_deadline_days.label'))
                    ->suffix(' dagen')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('municipality/resources/default-advice-question.columns.created_at.label'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('advisory_id')
                    ->label(__('municipality/resources/default-advice-question.filters.advisory.label'))
                    ->relationship('advisory', 'name'),

                SelectFilter::make('risico_classificatie')
                    ->label(__('municipality/resources/default-advice-question.filters.risico_classificatie.label'))
                    ->options([
                        'A' => 'A',
                        'B' => 'B',
                        'C' => 'C',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
