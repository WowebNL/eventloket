<?php

namespace App\Filament\Admin\Resources\MunicipalityResource\RelationManagers;

use App\Filament\Shared\Resources\MunicipalityVariables\Schemas\MunicipalityVariableForm;
use App\Filament\Shared\Resources\MunicipalityVariables\Tables\MunicipalityVariablesTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class VariablesRelationManager extends RelationManager
{
    protected static string $relationship = 'variables';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('resources/municipality_variable.plural_label');
    }

    public function form(Schema $schema): Schema
    {
        return MunicipalityVariableForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return MunicipalityVariablesTable::configure($table);
    }
}
