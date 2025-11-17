<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\DefaultAdviceQuestions\Schemas;

use Dotswan\MapPicker\Fields\Map;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class DefaultAdviceQuestionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('advisory_id')
                    ->label(__('municipality/resources/default-advice-question.form.advisory_id.label'))
                    ->relationship('advisory', 'name',)
                    ->required()
                    ->searchable()
                    ->preload(),

                Select::make('risico_classificatie')
                    ->label(__('municipality/resources/default-advice-question.form.risico_classificatie.label'))
                    ->options([
                        'A' => 'A',
                        'B' => 'B',
                        'C' => 'C',
                    ])
                    ->required(),

                TextInput::make('title')
                    ->label(__('municipality/resources/default-advice-question.form.title.label'))
                    ->required()
                    ->maxLength(255),

                Textarea::make('description')
                    ->label(__('municipality/resources/default-advice-question.form.description.label'))
                    ->required()
                    ->rows(5)
                    ->columnSpanFull(),

                TextInput::make('response_deadline_days')
                    ->label(__('municipality/resources/default-advice-question.form.response_deadline_days.label'))
                    ->helperText(__('municipality/resources/default-advice-question.form.response_deadline_days.helper'))
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->default(14),
            ]);
    }
}
