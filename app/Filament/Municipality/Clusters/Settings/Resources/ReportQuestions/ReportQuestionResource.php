<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\ReportQuestions;

use App\Filament\Municipality\Clusters\Settings;
use App\Filament\Municipality\Clusters\Settings\Resources\ReportQuestions\Pages\EditReportQuestion;
use App\Filament\Municipality\Clusters\Settings\Resources\ReportQuestions\Pages\ListReportQuestions;
use App\Filament\Shared\Resources\ReportQuestions\Schemas\ReportQuestionForm;
use App\Filament\Shared\Resources\ReportQuestions\Tables\ReportQuestionsTable;
use App\Models\ReportQuestion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReportQuestionResource extends Resource
{
    protected static ?string $model = ReportQuestion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?string $cluster = Settings::class;

    public static function getModelLabel(): string
    {
        return __('resources/report_question.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/report_question.plural_label');
    }

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return ReportQuestionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReportQuestionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReportQuestions::route('/'),
            'edit' => EditReportQuestion::route('/{record}/edit'),
        ];
    }
}
