<?php

namespace App\Filament\Clusters\AdminSettings\Resources\AdvisoryResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
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

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
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
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin/resources/municipality.columns.name.label')),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->multiple(),
            ])
            ->actions([
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                //
            ]);
    }
}
