<?php

namespace App\Filament\Admin\Resources\ZgwRequestLogs;

use App\Filament\Admin\Resources\ZgwRequestLogs\Pages\ListZgwRequestLogs;
use App\Models\ZgwRequestLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ZgwRequestLogResource extends Resource
{
    protected static ?string $model = ZgwRequestLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 10;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getModelLabel(): string
    {
        return __('admin/resources/zgw_request_log.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/resources/zgw_request_log.plural_label');
    }

    public static function getEloquentQuery(): Builder
    {
        // Admins see every municipality's logs plus the shared "main" connection
        // rows (municipality_id null), so there is no tenant scoping here.
        return parent::getEloquentQuery()
            ->with(['user', 'municipality.zgwConnection']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('admin/resources/zgw_request_log.columns.created_at.label'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('method')
                    ->label(__('admin/resources/zgw_request_log.columns.method.label'))
                    ->badge(),
                TextColumn::make('resource')
                    ->label(__('admin/resources/zgw_request_log.columns.resource.label'))
                    ->limit(60)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('status_code')
                    ->label(__('admin/resources/zgw_request_log.columns.status_code.label'))
                    ->badge()
                    ->color(fn (ZgwRequestLog $record): string => $record->failed ? 'danger' : 'success'),
                TextColumn::make('user.name')
                    ->label(__('admin/resources/zgw_request_log.columns.user.label'))
                    ->placeholder('—')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'user',
                        fn (Builder $q): Builder => $q->where('name', 'like', "%{$search}%"),
                    )),
                TextColumn::make('municipality.name')
                    ->label(__('admin/resources/zgw_request_log.columns.municipality.label'))
                    ->placeholder(__('admin/resources/zgw_request_log.columns.municipality.placeholder'))
                    ->sortable(),
                TextColumn::make('connection')
                    ->label(__('admin/resources/zgw_request_log.columns.connection.label'))
                    ->state(fn (ZgwRequestLog $record): string => $record->municipality?->zgwConnection?->displayName
                        ?: ZgwRequestLog::connectionLabel($record->connection))
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('failed')
                    ->label(__('admin/resources/zgw_request_log.filters.failed.label')),
                SelectFilter::make('method')
                    ->label(__('admin/resources/zgw_request_log.filters.method.label'))
                    ->options([
                        'GET' => 'GET',
                        'POST' => 'POST',
                        'PUT' => 'PUT',
                        'PATCH' => 'PATCH',
                        'DELETE' => 'DELETE',
                        'NOTIFY' => 'NOTIFY',
                    ]),
                SelectFilter::make('connection')
                    ->label(__('admin/resources/zgw_request_log.filters.connection.label'))
                    ->options(fn (): array => ZgwRequestLog::query()
                        ->distinct()
                        ->orderBy('connection')
                        ->pluck('connection')
                        ->mapWithKeys(fn (string $connection): array => [
                            $connection => ZgwRequestLog::connectionLabel($connection),
                        ])
                        ->all()),
                SelectFilter::make('municipality')
                    ->label(__('admin/resources/zgw_request_log.filters.municipality.label'))
                    ->relationship('municipality', 'name'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListZgwRequestLogs::route('/'),
        ];
    }
}
