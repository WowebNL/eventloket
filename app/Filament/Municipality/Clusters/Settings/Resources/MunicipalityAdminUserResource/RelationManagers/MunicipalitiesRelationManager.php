<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityAdminUserResource\RelationManagers;

use App\Enums\Role;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
                //
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
                    ->multiple()
                    ->recordSelectOptionsQuery(function (Builder $query) {
                        if (in_array(auth()->user()->role, [Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin])) {
                            return $query->whereHas('users', fn (Builder $query): Builder => $query->where('id', auth()->user()->id));
                        }

                        return $query;
                    }),
            ])
            ->recordActions([
                DetachAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
