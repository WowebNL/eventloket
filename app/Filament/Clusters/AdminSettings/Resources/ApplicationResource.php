<?php

namespace App\Filament\Clusters\AdminSettings\Resources;

use App\Filament\Clusters\AdminSettings;
use App\Filament\Clusters\AdminSettings\Resources\ApplicationResource\Pages\CreateApplication;
use App\Filament\Clusters\AdminSettings\Resources\ApplicationResource\Pages\EditApplication;
use App\Filament\Clusters\AdminSettings\Resources\ApplicationResource\Pages\ListApplications;
use App\Filament\Clusters\AdminSettings\Resources\ApplicationResource\RelationManagers\ClientsRelationManager;
use App\Filament\Clusters\AdminSettings\Resources\ApplicationResource\RelationManagers\TokensRelationManager;
use App\Models\Application;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ApplicationResource extends Resource
{
    protected static ?string $model = Application::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $cluster = AdminSettings::class;

    protected static bool $isScopedToTenant = false;

    public static function getModelLabel(): string
    {
        return __('admin/resources/application.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/resources/application.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('admin/resources/application.columns.name.label'))
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Checkbox::make('all_endpoints')
                    ->label(__('admin/resources/application.columns.all_endpoints.label')),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin/resources/application.columns.name.label'))
                    ->searchable(),
                IconColumn::make('all_endpoints')
                    ->label(__('admin/resources/application.columns.all_endpoints.label'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('info')
                    ->falseColor('warning'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label(__('admin/resources/application.columns.created_at.label')),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label(__('admin/resources/application.columns.updated_at.label')),
            ])
            ->filters([
                //
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
            'index' => ListApplications::route('/'),
            'create' => CreateApplication::route('/create'),
            'edit' => EditApplication::route('/{record}/edit'),
        ];
    }
}
