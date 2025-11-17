<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\DefaultAdviceQuestions;

use App\Filament\Municipality\Clusters\Settings;
use App\Filament\Municipality\Clusters\Settings\Resources\DefaultAdviceQuestions\Pages\CreateDefaultAdviceQuestion;
use App\Filament\Municipality\Clusters\Settings\Resources\DefaultAdviceQuestions\Pages\EditDefaultAdviceQuestion;
use App\Filament\Municipality\Clusters\Settings\Resources\DefaultAdviceQuestions\Pages\ListDefaultAdviceQuestions;
use App\Filament\Shared\Resources\DefaultAdviceQuestions\Schemas\DefaultAdviceQuestionForm;
use App\Filament\Shared\Resources\DefaultAdviceQuestions\Tables\DefaultAdviceQuestionTable;
use App\Models\DefaultAdviceQuestion;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DefaultAdviceQuestionResource extends Resource
{
    protected static ?string $model = DefaultAdviceQuestion::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $cluster = Settings::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('resources/default_advice_question.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/default_advice_question.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return DefaultAdviceQuestionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DefaultAdviceQuestionTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDefaultAdviceQuestions::route('/'),
            'create' => CreateDefaultAdviceQuestion::route('/create'),
            'edit' => EditDefaultAdviceQuestion::route('/{record}/edit'),
        ];
    }
}
