<?php

namespace App\Filament\Municipality\Resources\Zaken\ZaakResource\Resources\OrganiserThreads\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrganiserThreadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('title')
                            ->label(__('resources/organiser_thread.form.title.label'))
                            ->required(),
                        RichEditor::make('body')
                            ->label(__('resources/organiser_thread.form.body.label'))
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'strike', 'link'],
                                ['h1', 'h2', 'h3', 'alignStart', 'alignCenter', 'alignEnd'],
                                ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
                            ]),
                    ]),
            ]);
    }
}
