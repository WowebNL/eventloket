<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\AdminUserResource\Pages\CreateAdminUser;
use App\Filament\Admin\Resources\AdminUserResource\Pages\EditAdminUser;
use App\Filament\Admin\Resources\AdminUserResource\Pages\ListAdminUsers;
use App\Filament\Shared\Pages\EditProfile;
use App\Models\User;
use App\Models\Users\AdminUser;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AdminUserResource extends Resource
{
    protected static ?string $model = AdminUser::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 0;

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
                EditProfile::getFirstNameFormComponent(),
                EditProfile::getLastNameFormComponent(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin/resources/admin.columns.name.label'))
                    ->description(fn (User $record): string => $record->email)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->label(__('admin/resources/admin.columns.role.label')),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                RestoreAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminUsers::route('/'),
            'create' => CreateAdminUser::route('/create'),
            'edit' => EditAdminUser::route('/{record}/edit'),
        ];
    }
}
