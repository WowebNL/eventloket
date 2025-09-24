<?php

namespace App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\OrganiserThreads;

use App\Filament\Shared\Resources\Zaken\ZaakResource;
use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\OrganiserThreads\Pages\CreateOrganiserThread;
use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\OrganiserThreads\Pages\EditOrganiserThread;
use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\OrganiserThreads\Pages\ViewOrganiserThread;
use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\OrganiserThreads\Schemas\OrganiserThreadForm;
use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\OrganiserThreads\Schemas\OrganiserThreadInfolist;
use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\OrganiserThreads\Tables\OrganiserThreadsTable;
use App\Models\Threads\OrganiserThread;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OrganiserThreadResource extends Resource
{
    protected static ?string $model = OrganiserThread::class;

    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $parentResource = ZaakResource::class;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('resources/organiser_thread.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/organiser_thread.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return OrganiserThreadForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrganiserThreadInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrganiserThreadsTable::configure($table);
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
            'create' => CreateOrganiserThread::route('/create'),
            'view' => ViewOrganiserThread::route('/{record}'),
            'edit' => EditOrganiserThread::route('/{record}/edit'),
        ];
    }
}
