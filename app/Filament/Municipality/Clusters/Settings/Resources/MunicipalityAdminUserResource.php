<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources;

use App\Enums\Role;
use App\Filament\Municipality\Clusters\Settings;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityAdminUserResource\Pages\CreateMunicipalityAdminUser;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityAdminUserResource\Pages\EditMunicipalityAdminUser;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityAdminUserResource\Pages\ListMunicipalityAdminUsers;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityAdminUserResource\RelationManagers\MunicipalitiesRelationManager;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MunicipalityAdminUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 0;

    protected static ?string $cluster = Settings::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $tenantOwnershipRelationshipName = 'municipalities';

    public static function getModelLabel(): string
    {
        return __('admin/resources/admin.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/resources/admin.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('admin/resources/admin.columns.name.label')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin/resources/admin.columns.name.label'))
                    ->description(fn (User $record): string => $record->email)
                    ->searchable(),
                TextColumn::make('role')
                    ->label(__('admin/resources/admin.columns.role.label')),
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
            MunicipalitiesRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('role', [Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMunicipalityAdminUsers::route('/'),
            'create' => CreateMunicipalityAdminUser::route('/create'),
            'edit' => EditMunicipalityAdminUser::route('/{record}/edit'),
        ];
    }
}
