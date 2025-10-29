<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityVariables;

use App\Filament\Municipality\Clusters\Settings;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityVariables\Pages\CreateMunicipalityVariable;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityVariables\Pages\EditMunicipalityVariable;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityVariables\Pages\ListMunicipalityVariables;
use App\Filament\Shared\Resources\MunicipalityVariables\Schemas\MunicipalityVariableForm;
use App\Filament\Shared\Resources\MunicipalityVariables\Tables\MunicipalityVariablesTable;
use App\Models\MunicipalityVariable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MunicipalityVariableResource extends Resource
{
    protected static ?string $model = MunicipalityVariable::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $cluster = Settings::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('resources/municipality_variable.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/municipality_variable.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return MunicipalityVariableForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MunicipalityVariablesTable::configure($table);
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
            'index' => ListMunicipalityVariables::route('/'),
            'create' => CreateMunicipalityVariable::route('/create'),
            'edit' => EditMunicipalityVariable::route('/{record}/edit'),
        ];
    }
}
