<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\Locations;

use App\Filament\Municipality\Clusters\Settings;
use App\Filament\Municipality\Clusters\Settings\Resources\Locations\Pages\CreateLocation;
use App\Filament\Municipality\Clusters\Settings\Resources\Locations\Pages\EditLocation;
use App\Filament\Municipality\Clusters\Settings\Resources\Locations\Pages\ListLocations;
use App\Filament\Shared\Resources\Locations\Schemas\LocationForm;
use App\Filament\Shared\Resources\Locations\Tables\LocationsTable;
use App\Models\Location;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $cluster = Settings::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('resources/location.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/location.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return LocationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LocationsTable::configure($table);
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
            'index' => ListLocations::route('/'),
            'create' => CreateLocation::route('/create'),
            'edit' => EditLocation::route('/{record}/edit'),
        ];
    }
}
