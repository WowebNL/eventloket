<?php

namespace App\Filament\Shared\Resources\ReportQuestions\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReportQuestionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order')
                    ->label(__('resources/report_question.columns.order.label'))
                    ->numeric()
                    ->sortable()
                    ->badge(),
                TextColumn::make('question')
                    ->label(__('resources/report_question.columns.question.label'))
                    ->searchable()
                    ->limit(80)
                    ->wrap(),
                IconColumn::make('is_active')
                    ->label(__('resources/report_question.columns.is_active.label'))
                    ->boolean(),
                TextColumn::make('placeholder_value')
                    ->label(__('resources/report_question.columns.placeholder_value.label'))
                    ->searchable()
                    ->default('-')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label(__('resources/report_question.columns.updated_at.label'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->defaultSort('order', 'asc')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
