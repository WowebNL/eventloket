<?php

namespace App\Filament\Clusters\AdminSettings\Resources;

use App\Enums\Role;
use App\Filament\Clusters\AdminSettings;
use App\Filament\Clusters\AdminSettings\Resources\AdminResource\Pages;
use App\Filament\Clusters\AdminSettings\Resources\AdminResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdminResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 0;

    protected static ?string $cluster = AdminSettings::class;

    public static function isScopedToTenant(): bool
    {
        if (auth()->user()->role === Role::Admin) {
            return false;
        }

        return true;
    }

    protected static ?string $tenantOwnershipRelationshipName = 'municipalities';

    public static function getModelLabel(): string
    {
        return __('admin/resources/admin.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/resources/admin.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('admin/resources/admin.columns.name.label')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin/resources/admin.columns.name.label'))
                    ->description(fn (User $record): string => $record->email)
                    ->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->label(__('admin/resources/admin.columns.role.label')),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('role', [Role::Admin, Role::MunicipalityAdmin]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\MunicipalitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdmins::route('/'),
            'create' => Pages\CreateAdmin::route('/create'),
            'edit' => Pages\EditAdmin::route('/{record}/edit'),
        ];
    }
}
