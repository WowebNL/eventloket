<?php

namespace App\Filament\Clusters\AdminSettings\Resources;

use App\Filament\Clusters\AdminSettings;
use App\Filament\Clusters\AdminSettings\Resources\AdvisoryResource\Pages;
use App\Models\Advisory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AdvisoryResource extends Resource
{
    protected static ?string $model = Advisory::class;

    protected static ?string $cluster = AdminSettings::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-lifebuoy';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('admin/resources/advisory.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/resources/advisory.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AdvisoryResource\RelationManagers\UsersRelationManager::class,
            AdvisoryResource\RelationManagers\MunicipalitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdvisories::route('/'),
            'create' => Pages\CreateAdvisory::route('/create'),
            'edit' => Pages\EditAdvisory::route('/{record}/edit'),
        ];
    }
}
