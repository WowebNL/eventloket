<?php

namespace App\Filament\Municipality\Resources\Zaken;

use App\Filament\Municipality\Resources\Zaken\Pages\ListZaken;
use App\Filament\Municipality\Resources\Zaken\Pages\ViewZaak;
use App\Filament\Municipality\Resources\Zaken\Schemas\ZaakForm;
use App\Filament\Municipality\Resources\Zaken\Schemas\ZaakInfolist;
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

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static ?string $recordTitleAttribute = 'event_name';

    protected static ?string $tenantOwnershipRelationshipName = 'municipality';

    protected static ?string $slug = 'zaken';

    public static function getModelLabel(): string
    {
        return __('municipality/resources/zaak.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('municipality/resources/zaak.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return ZaakForm::configure($schema);
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
