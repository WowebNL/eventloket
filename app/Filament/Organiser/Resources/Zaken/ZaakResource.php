<?php

namespace App\Filament\Organiser\Resources\Zaken;

use App\Filament\Organiser\Resources\Zaken\Pages\ListZaken;
use App\Filament\Organiser\Resources\Zaken\Pages\ViewZaak;
use App\Filament\Organiser\Resources\Zaken\Schemas\ZaakInfolist;
use App\Filament\Shared\Resources\Zaken\Tables\ZakenTable;
use App\Models\Zaak;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ZaakResource extends Resource
{
    protected static ?string $model = Zaak::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'event_name';

    protected static ?string $tenantOwnershipRelationshipName = 'organisation';

    protected static ?string $slug = 'zaken';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function getModelLabel(): string
    {
        return __('organiser/resources/zaak.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('organiser/resources/zaak.plural_label');
    }

    public static function infolist(Schema $schema): Schema
    {
        return ZaakInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ZakenTable::configure($table);
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
            'index' => ListZaken::route('/'),
            'view' => ViewZaak::route('/{record}'),
        ];
    }
}
