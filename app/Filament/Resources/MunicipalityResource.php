<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MunicipalityResource\Pages\CreateMunicipality;
use App\Filament\Resources\MunicipalityResource\Pages\EditMunicipality;
use App\Filament\Resources\MunicipalityResource\Pages\ListMunicipalities;
use App\Models\Municipality;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MunicipalityResource extends Resource
{
    protected static ?string $model = Municipality::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static ?int $navigationSort = 1;

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
                Select::make('zaaktypen')
                    ->label(__('admin/resources/municipality.columns.zaaktypen.label'))
                    ->multiple()
                    ->relationship(name: 'zaaktypen', titleAttribute: 'name', modifyQueryUsing: fn ($query) => $query->where(['is_active' => true, 'municipality_id' => null]))
                    ->preload(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin/resources/municipality.columns.name.label'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('brk_identification')
                    ->label(__('admin/resources/municipality.columns.brk_identification.label'))
                    ->searchable(),
                TextColumn::make('zaaktypen.name')
                    ->sortable(),
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
