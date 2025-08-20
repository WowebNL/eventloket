<?php

namespace App\Filament\Clusters\AdminSettings\Resources;

use App\Enums\Role;
use App\Filament\Clusters\AdminSettings;
use App\Filament\Clusters\AdminSettings\Resources\ApplicationResource\Pages;
use App\Filament\Clusters\AdminSettings\Resources\ApplicationResource\RelationManagers\ClientsRelationManager;
use App\Filament\Clusters\AdminSettings\Resources\ApplicationResource\RelationManagers\TokensRelationManager;
use App\Models\Application;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ApplicationResource extends Resource
{
    protected static ?string $model = Application::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $cluster = AdminSettings::class;

    public static function isScopedToTenant(): bool
    {
        if (auth()->user()->role === Role::Admin) {
            return false;
        }

        return true;
    }

    public static function getModelLabel(): string
    {
        return __('admin/resources/application.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/resources/application.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('admin/resources/application.columns.name.label'))
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\Checkbox::make('all_endpoints')
                    ->label(__('admin/resources/application.columns.all_endpoints.label')),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin/resources/application.columns.name.label'))
                    ->searchable(),
                Tables\Columns\IconColumn::make('all_endpoints')
                    ->label(__('admin/resources/application.columns.all_endpoints.label'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('info')
                    ->falseColor('warning'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label(__('admin/resources/application.columns.created_at.label')),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label(__('admin/resources/application.columns.updated_at.label')),
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
            ClientsRelationManager::class,
            TokensRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApplications::route('/'),
            'create' => Pages\CreateApplication::route('/create'),
            'edit' => Pages\EditApplication::route('/{record}/edit'),
        ];
    }
}
