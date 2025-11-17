<?php

namespace App\Filament\Shared\Resources\DefaultAdviceQuestions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DefaultAdviceQuestionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('advisory_id')
                    ->label(__('resources/default_advice_question.form.advisory_id.label'))
                    ->relationship('advisory', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),

                Select::make('risico_classificatie')
                    ->label(__('resources/default_advice_question.form.risico_classificatie.label'))
                    ->options([
                        'A' => 'A',
                        'B' => 'B',
                        'C' => 'C',
                    ])
                    ->required(),

                TextInput::make('title')
                    ->label(__('resources/default_advice_question.form.title.label'))
                    ->required()
                    ->maxLength(255),

                Textarea::make('description')
                    ->label(__('resources/default_advice_question.form.description.label'))
                    ->required()
                    ->rows(5)
                    ->columnSpanFull(),

                TextInput::make('response_deadline_days')
                    ->label(__('resources/default_advice_question.form.response_deadline_days.label'))
                    ->helperText(__('resources/default_advice_question.form.response_deadline_days.helper'))
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->default(14),
            ]);
    }
}
