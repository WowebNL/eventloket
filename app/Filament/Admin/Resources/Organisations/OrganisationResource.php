<?php

namespace App\Filament\Admin\Resources\Organisations;

use App\Enums\OrganisationType;
use App\Filament\Admin\Resources\Organisations\Pages\CreateOrganisation;
use App\Filament\Admin\Resources\Organisations\Pages\EditOrganisation;
use App\Filament\Admin\Resources\Organisations\Pages\ListOrganisations;
use App\Filament\Admin\Resources\Organisations\Schemas\OrganisationForm;
use App\Filament\Admin\Resources\Organisations\Tables\OrganisationsTable;
use App\Models\Organisation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrganisationResource extends Resource
{
    protected static ?string $model = Organisation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return OrganisationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrganisationsTable::configure($table);
    }

    public static function getModelLabel(): string
    {
        return __('admin/resources/organisation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/resources/organisation.plural_label');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\UsersRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', OrganisationType::Business);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganisations::route('/'),
            'create' => CreateOrganisation::route('/create'),
            'edit' => EditOrganisation::route('/{record}/edit'),
        ];
    }
}
