<?php

namespace App\Filament\Admin\Resources\MunicipalityResource\RelationManagers;

use App\Filament\Shared\Resources\MunicipalityFormQuestions\Concerns\SafelyReordersMunicipalityFormQuestions;
use App\Filament\Shared\Resources\MunicipalityFormQuestions\Schemas\MunicipalityFormQuestionForm;
use App\Filament\Shared\Resources\MunicipalityFormQuestions\Tables\MunicipalityFormQuestionsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MunicipalityFormQuestionsRelationManager extends RelationManager
{
    use SafelyReordersMunicipalityFormQuestions;

    protected static string $relationship = 'formQuestions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('resources/municipality_form_question.plural_label');
    }

    public function form(Schema $schema): Schema
    {
        return MunicipalityFormQuestionForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return MunicipalityFormQuestionsTable::configure($table);
    }
}
