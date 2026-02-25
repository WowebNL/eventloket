<?php

namespace App\Filament\Admin\Resources\Zaaktypes;

use App\Filament\Admin\Resources\Zaaktypes\Pages\CreateZaaktype;
use App\Filament\Admin\Resources\Zaaktypes\Pages\EditZaaktype;
use App\Filament\Admin\Resources\Zaaktypes\Pages\ListZaaktypes;
use App\Filament\Admin\Resources\Zaaktypes\Schemas\ZaaktypeForm;
use App\Filament\Admin\Resources\Zaaktypes\Tables\ZaaktypesTable;
use App\Models\Zaaktype;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ZaaktypeResource extends Resource
{
    protected static ?string $model = Zaaktype::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    public static function getModelLabel(): string
    {
        return __('admin/resources/zaaktype.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/resources/zaaktype.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return ZaaktypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ZaaktypesTable::configure($table);
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
            'index' => ListZaaktypes::route('/'),
            'create' => CreateZaaktype::route('/create'),
            'edit' => EditZaaktype::route('/{record}/edit'),
        ];
    }
}
