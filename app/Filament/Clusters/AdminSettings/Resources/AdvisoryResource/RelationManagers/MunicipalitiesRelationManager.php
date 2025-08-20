<?php

namespace App\Filament\Clusters\AdminSettings\Resources\AdvisoryResource\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MunicipalitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'municipalities';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin/resources/municipality.plural_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin/resources/municipality.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/resources/municipality.plural_label');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('admin/resources/municipality.columns.name.label'))
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin/resources/municipality.columns.name.label')),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->multiple(),
            ])
            ->recordActions([
                DetachAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
