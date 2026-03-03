<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Actions\Reset2faAction;
use App\Filament\Admin\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use App\Models\Users\AdvisorUser;
use App\Models\Users\OrganiserUser;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('admin/resources/all-users.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/resources/all-users.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                TextEntry::make('name')
                    ->label(__('admin/resources/all-users.columns.name.label')),
                TextEntry::make('email')
                    ->label(__('admin/resources/all-users.infolist.email')),
                TextEntry::make('role')
                    ->label(__('admin/resources/all-users.columns.role.label'))
                    ->badge(),

                TextEntry::make('municipalities.name')
                    ->visible(fn (User $record) => $record->municipalities()->exists())
                    ->label(__('admin/resources/all-users.infolist.municipalities'))
                    ->badge()
                    ->placeholder(__('admin/resources/all-users.infolist.no_municipalities')),

                TextEntry::make('organisations.name')
                    ->visible(fn (User $record) => $record instanceof OrganiserUser && $record->organisations()->exists())
                    ->label(__('admin/resources/all-users.infolist.organisations'))
                    ->badge()
                    ->placeholder(__('admin/resources/all-users.infolist.no_organisations')),

                TextEntry::make('advisories.name')
                    ->visible(fn (User $record) => $record instanceof AdvisorUser && $record->advisories()->exists())
                    ->label(__('admin/resources/all-users.infolist.advisories'))
                    ->badge()
                    ->placeholder(__('admin/resources/all-users.infolist.no_advisories')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin/resources/all-users.columns.name.label'))
                    ->description(fn (User $record): string => $record->email)
                    ->searchable(['name', 'email', 'first_name', 'last_name'])
                    ->sortable(),
                TextColumn::make('role')
                    ->label(__('admin/resources/all-users.columns.role.label'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('email_verified_at')
                    ->label(__('admin/resources/all-users.columns.email_verified.label'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('admin/resources/all-users.columns.created_at.label'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                RestoreAction::make(),
                ViewAction::make(),
                Reset2faAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
        ];
    }
}
