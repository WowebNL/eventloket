<?php

namespace App\Filament\Shared\Resources\DefaultAdviceQuestions\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DefaultAdviceQuestionTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modelLabel(__('resources/default_advice_question.label'))
            ->pluralModelLabel(__('resources/default_advice_question.plural_label'))
            ->columns([
                TextColumn::make('advisory.name')
                    ->label(__('resources/default_advice_question.columns.advisory.label'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('risico_classificatie')
                    ->label(__('resources/default_advice_question.columns.risico_classificatie.label'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('title')
                    ->label(__('resources/default_advice_question.columns.title.label'))
                    ->searchable()
                    ->limit(50),

                TextColumn::make('response_deadline_days')
                    ->label(__('resources/default_advice_question.columns.response_deadline_days.label'))
                    ->suffix(__('resources/default_advice_question.columns.response_deadline_days.suffix'))
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('resources/default_advice_question.columns.created_at.label'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('advisory_id')
                    ->label(__('resources/default_advice_question.filters.advisory.label'))
                    ->relationship('advisory', 'name'),

                SelectFilter::make('risico_classificatie')
                    ->label(__('resources/default_advice_question.filters.risico_classificatie.label'))
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
