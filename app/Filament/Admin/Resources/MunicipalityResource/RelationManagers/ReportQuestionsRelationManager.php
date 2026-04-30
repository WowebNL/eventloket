<?php

namespace App\Filament\Admin\Resources\MunicipalityResource\RelationManagers;

use App\Filament\Shared\Resources\ReportQuestions\Schemas\ReportQuestionForm;
use App\Filament\Shared\Resources\ReportQuestions\Tables\ReportQuestionsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ReportQuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'reportQuestions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('resources/report_question.plural_label');
    }

    public function form(Schema $schema): Schema
    {
        return ReportQuestionForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return ReportQuestionsTable::configure($table);
    }
}
