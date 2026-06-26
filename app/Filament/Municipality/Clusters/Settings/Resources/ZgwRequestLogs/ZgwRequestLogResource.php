<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\ZgwRequestLogs;

use App\Enums\Role;
use App\Filament\Municipality\Clusters\Settings;
use App\Filament\Municipality\Clusters\Settings\Resources\ZgwRequestLogs\Pages\ListZgwRequestLogs;
use App\Models\ZgwRequestLog;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ZgwRequestLogResource extends Resource
{
    protected static ?string $model = ZgwRequestLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $cluster = Settings::class;

    protected static ?int $navigationSort = 5;

    protected static ?string $tenantOwnershipRelationshipName = 'municipality';

    public static function canAccess(): bool
    {
        return in_array(auth()->user()->role, [
            Role::KoppelingBeheerder,
            Role::MunicipalityAdmin,
            Role::ReviewerMunicipalityAdmin,
        ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        // Isolate each municipality to its own logs; "main"-connection logs
        // (municipality_id null) belong to no tenant and are excluded here.
        return parent::getEloquentQuery()
            ->where('municipality_id', Filament::getTenant()?->getKey())
            ->with(['user', 'municipality.zgwConnection']);
    }

    public static function getModelLabel(): string
    {
        return __('municipality/resources/zgw_request_log.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('municipality/resources/zgw_request_log.plural_label');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('municipality/resources/zgw_request_log.columns.created_at.label'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('method')
                    ->label(__('municipality/resources/zgw_request_log.columns.method.label'))
                    ->badge(),
                TextColumn::make('resource')
                    ->label(__('municipality/resources/zgw_request_log.columns.resource.label'))
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('status_code')
                    ->label(__('municipality/resources/zgw_request_log.columns.status_code.label'))
                    ->badge()
                    ->color(fn (ZgwRequestLog $record): string => $record->failed ? 'danger' : 'success'),
                TextColumn::make('user.name')
                    ->label(__('municipality/resources/zgw_request_log.columns.user.label'))
                    ->placeholder('—'),
                TextColumn::make('connection')
                    ->label(__('municipality/resources/zgw_request_log.columns.connection.label'))
                    // Prefer the connection's friendly label when one is set.
                    ->state(fn (ZgwRequestLog $record): string => $record->municipality?->zgwConnection?->name ?: $record->connection)
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('failed')
                    ->label(__('municipality/resources/zgw_request_log.filters.failed.label')),
                SelectFilter::make('method')
                    ->label(__('municipality/resources/zgw_request_log.filters.method.label'))
                    ->options([
                        'GET' => 'GET',
                        'POST' => 'POST',
                        'PUT' => 'PUT',
                        'PATCH' => 'PATCH',
                        'DELETE' => 'DELETE',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListZgwRequestLogs::route('/'),
        ];
    }
}
