<?php

namespace App\Filament\Municipality\Resources\Zaken\ZaakResource\Resources\AdviceThreads\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AdviceThreadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('advisory_id')
                    ->label(__('resources/advice_thread.form.advisory_id.label'))
                    ->relationship('advisory', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                DateTimePicker::make('advice_due_at')
                    ->default(now()->addDays(10)->setTime(17, 0))
                    ->label(__('resources/advice_thread.form.advice_due_at.label')),

                Section::make()
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('title')
                            ->label(__('resources/advice_thread.form.title.label'))
                            ->required(),
                        RichEditor::make('body')
                            ->label(__('resources/advice_thread.form.body.label'))
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'strike', 'link'],
                                ['h1', 'h2', 'h3', 'alignStart', 'alignCenter', 'alignEnd'],
                                ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
                            ]),
                    ]),

            ]);
    }
}
