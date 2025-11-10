<?php

namespace App\Filament\Admin\Resources\MunicipalityResource\RelationManagers;

use App\Filament\Shared\Resources\Locations\Schemas\LocationForm;
use App\Filament\Shared\Resources\Locations\Tables\LocationsTable;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class LocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'locations';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('resources/location.plural_label');
    }

    public function form(Schema $schema): Schema
    {
        return LocationForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return LocationsTable::configure($table)
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
