<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\AdvisoryResource\Pages\CreateAdvisory;
use App\Filament\Admin\Resources\AdvisoryResource\Pages\EditAdvisory;
use App\Filament\Admin\Resources\AdvisoryResource\Pages\ListAdvisories;
use App\Filament\Admin\Resources\AdvisoryResource\RelationManagers\MunicipalitiesRelationManager;
use App\Filament\Admin\Resources\AdvisoryResource\RelationManagers\UsersRelationManager;
use App\Models\Advisory;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdvisoryResource extends Resource
{
    protected static ?string $model = Advisory::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-lifebuoy';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('admin/resources/advisory.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/resources/advisory.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('admin/resources/advisory.columns.name.label'))
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin/resources/advisory.columns.name.label'))
                    ->searchable()
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
            UsersRelationManager::class,
            MunicipalitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdvisories::route('/'),
            'create' => CreateAdvisory::route('/create'),
            'edit' => EditAdvisory::route('/{record}/edit'),
        ];
    }
}
