<?php

namespace App\Filament\Shared\Resources\ReportQuestions\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ReportQuestionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('order')
                    ->label(__('resources/report_question.form.order.label'))
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('question')
                    ->label(__('resources/report_question.form.question.label'))
                    ->helperText(__('resources/report_question.form.question.helper_text'))
                    ->maxLength(1000)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label(__('resources/report_question.form.is_active.label'))
                    ->helperText(__('resources/report_question.form.is_active.helper_text'))
                    ->default(true),
            ]);
    }
}
