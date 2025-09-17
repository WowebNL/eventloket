<?php

namespace App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads;

use App\Filament\Shared\Resources\Zaken\ZaakResource;
use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\Pages\CreateAdviceThread;
use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\Pages\EditAdviceThread;
use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\Pages\ViewAdviceThread;
use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\Schemas\AdviceThreadForm;
use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\Schemas\AdviceThreadInfolist;
use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\Tables\AdviceThreadsTable;
use App\Models\Threads\AdviceThread;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AdviceThreadResource extends Resource
{
    protected static ?string $model = AdviceThread::class;

    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $parentResource = ZaakResource::class;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('resources/advice_thread.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/advice_thread.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return AdviceThreadForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AdviceThreadInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdviceThreadsTable::configure($table);
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
            'create' => CreateAdviceThread::route('/create'),
            'view' => ViewAdviceThread::route('/{record}'),
            'edit' => EditAdviceThread::route('/{record}/edit'),
        ];
    }
}
