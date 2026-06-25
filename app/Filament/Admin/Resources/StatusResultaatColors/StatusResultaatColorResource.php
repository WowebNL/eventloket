<?php

namespace App\Filament\Admin\Resources\StatusResultaatColors;

use App\Filament\Admin\Resources\StatusResultaatColors\Pages\CreateStatusResultaatColor;
use App\Filament\Admin\Resources\StatusResultaatColors\Pages\EditStatusResultaatColor;
use App\Filament\Admin\Resources\StatusResultaatColors\Pages\ListStatusResultaatColors;
use App\Filament\Admin\Resources\StatusResultaatColors\Schemas\StatusResultaatColorForm;
use App\Filament\Admin\Resources\StatusResultaatColors\Tables\StatusResultaatColorsTable;
use App\Models\StatusResultaatColor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StatusResultaatColorResource extends Resource
{
    protected static ?string $model = StatusResultaatColor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('admin/resources/status_resultaat_color.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/resources/status_resultaat_color.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return StatusResultaatColorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StatusResultaatColorsTable::configure($table);
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
            'index' => ListStatusResultaatColors::route('/'),
            'create' => CreateStatusResultaatColor::route('/create'),
            'edit' => EditStatusResultaatColor::route('/{record}/edit'),
        ];
    }
}
