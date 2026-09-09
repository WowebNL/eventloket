<?php

namespace App\Filament\Admin\Resources\Zaaktypes\Tables;

use App\Enums\ZaaktypeRole;
use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use App\Models\Zaaktype;
use App\Services\Zgw\ZgwConnectionResolver;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class ZaaktypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin/resources/zaaktype.columns.name.label'))
                    ->searchable(),
                TextColumn::make('role')
                    ->label(__('admin/resources/zaaktype.columns.role.label'))
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('municipality.name')
                    ->label(__('admin/resources/zaaktype.columns.municipality.label'))
                    ->searchable(),
                TextColumn::make('connection')
                    ->label(__('admin/resources/zaaktype.columns.connection.label'))
                    ->badge()
                    ->state(fn (Zaaktype $record): string => self::connectionLabel($record))
                    ->color(fn (Zaaktype $record): string => self::isMismatch($record) ? 'warning' : 'gray')
                    ->icon(fn (Zaaktype $record): ?string => self::isMismatch($record) ? 'heroicon-o-exclamation-triangle' : null)
                    ->tooltip(fn (Zaaktype $record): ?string => self::isMismatch($record)
                        ? __('admin/resources/zaaktype.columns.connection.mismatch_tooltip')
                        : null),
                IconColumn::make('is_active')
                    ->label(__('admin/resources/zaaktype.columns.is_active.label'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('admin/resources/zaaktype.columns.created_at.label'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('admin/resources/zaaktype.columns.updated_at.label'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->groups([
                Group::make('connection')
                    ->label(__('admin/resources/zaaktype.columns.connection.label'))
                    ->getTitleFromRecordUsing(fn (Zaaktype $record): string => self::connectionLabel($record)),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label(__('admin/resources/zaaktype.columns.role.label'))
                    ->options(ZaaktypeRole::class),
                SelectFilter::make('connection')
                    ->label(__('admin/resources/zaaktype.columns.connection.label'))
                    ->options(fn (): array => Zaaktype::query()
                        ->distinct()
                        ->orderBy('connection')
                        ->pluck('connection')
                        ->mapWithKeys(fn (string $connection): array => [
                            $connection => $connection === ZgwConnectionResolver::DEFAULT_CONNECTION
                                ? __('admin/resources/zaaktype.columns.connection.main')
                                : (MunicipalityZgwConnection::displayNameForConnection($connection) ?? $connection),
                        ])
                        ->all()),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * The friendly source-instance label for a zaaktype: "Hoofdcatalogus" for the
     * shared main connection, otherwise the municipality connection's display name.
     */
    private static function connectionLabel(Zaaktype $record): string
    {
        if ($record->connection === ZgwConnectionResolver::DEFAULT_CONNECTION) {
            return __('admin/resources/zaaktype.columns.connection.main');
        }

        return $record->municipality?->zgwConnection?->displayName ?: $record->connection;
    }

    /**
     * Whether this is a main-catalogus zaaktype whose name points at a municipality
     * that runs its own ZGW instance, meaning the municipality almost certainly uses
     * its own zaaktype and this row is not the one in use.
     */
    private static function isMismatch(Zaaktype $record): bool
    {
        if ($record->connection !== ZgwConnectionResolver::DEFAULT_CONNECTION) {
            return false;
        }

        if (! preg_match('/\bgemeente\s+(.+)$/iu', (string) $record->name, $matches)) {
            return false;
        }

        return in_array(strtolower(trim($matches[1])), self::ownInstanceMunicipalityNames(), true);
    }

    /**
     * Lowercased names of municipalities that have their own ZGW connection,
     * resolved once per request.
     *
     * @return array<int, string>
     */
    private static function ownInstanceMunicipalityNames(): array
    {
        return once(fn (): array => Municipality::query()
            ->whereHas('zgwConnection')
            ->pluck('name')
            ->map(fn (string $name): string => strtolower($name))
            ->all());
    }
}
