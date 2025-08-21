<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources;

use App\Filament\Municipality\Clusters\Settings;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityResource\Pages\CreateMunicipality;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityResource\Pages\EditMunicipality;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityResource\Pages\ListMunicipalities;
use App\Models\Municipality;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MunicipalityResource extends Resource
{
    protected static ?string $model = Municipality::class;

    protected static ?string $cluster = Settings::class;

    protected static bool $isScopedToTenant = false;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static ?int $navigationSort = 0;

    public static function getModelLabel(): string
    {
        return __('admin/resources/municipality.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/resources/municipality.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('admin/resources/municipality.columns.name.label'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('brk_identification')
                    ->label(__('admin/resources/municipality.columns.brk_identification.label'))
                    ->required()
                    ->startsWith('GM')
                    ->helperText(__('admin/resources/municipality.columns.brk_identification.helper_text'))
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin/resources/municipality.columns.name.label'))
                    ->searchable(),
                TextColumn::make('brk_identification')
                    ->label(__('admin/resources/municipality.columns.brk_identification.label'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
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
            'index' => ListMunicipalities::route('/'),
            'create' => CreateMunicipality::route('/create'),
            'edit' => EditMunicipality::route('/{record}/edit'),
        ];
    }
}
