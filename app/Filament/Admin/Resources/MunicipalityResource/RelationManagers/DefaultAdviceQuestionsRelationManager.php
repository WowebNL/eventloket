<?php

namespace App\Filament\Admin\Resources\MunicipalityResource\RelationManagers;

use App\Filament\Shared\Resources\DefaultAdviceQuestions\Schemas\DefaultAdviceQuestionForm;
use App\Filament\Shared\Resources\DefaultAdviceQuestions\Tables\DefaultAdviceQuestionTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DefaultAdviceQuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'defaultAdviceQuestions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('resources/default_advice_question.plural_label');
    }

    public function form(Schema $schema): Schema
    {
        return DefaultAdviceQuestionForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return DefaultAdviceQuestionTable::configure($table);
    }
}
