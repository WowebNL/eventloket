<?php

namespace App\Filament\Shared\Resources\ReportQuestions\Tables;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReportQuestionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading(__('resources/report_question.plural_label'))
            ->description(__('resources/report_question.table.description'))
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
                TextColumn::make('updated_at')
                    ->label(__('resources/report_question.columns.updated_at.label'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->defaultSort('order', 'asc')
            ->reorderable('order')
            ->reorderRecordsTriggerAction(
                fn (Action $action, bool $isReordering) => $action
                    ->button()
                    ->label($isReordering ? __('resources/report_question.actions.disable_reordering.label') : __('resources/report_question.actions.enable_reordering.label')),
            )
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
