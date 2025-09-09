<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources;

use App\Enums\Role;
use App\Filament\Municipality\Clusters\Settings;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityAdminUserResource\Pages\CreateMunicipalityAdminUser;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityAdminUserResource\Pages\EditMunicipalityAdminUser;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityAdminUserResource\Pages\ListMunicipalityAdminUsers;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityAdminUserResource\RelationManagers\MunicipalitiesRelationManager;
use App\Models\User;
use App\Models\Users\MunicipalityUser;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MunicipalityAdminUserResource extends Resource
{
    protected static ?string $model = MunicipalityUser::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 0;

    protected static ?string $cluster = Settings::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $tenantOwnershipRelationshipName = 'municipalities';

    public static function getModelLabel(): string
    {
        return __('municipality/resources/municipality_admin.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('municipality/resources/municipality_admin.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('municipality/resources/municipality_admin.columns.name.label')),
                Select::make('role')
                    ->label(__('municipality/resources/municipality_admin.columns.role.label'))
                    ->options([
                        Role::Reviewer->value => Role::Reviewer->getLabel(),
                        Role::ReviewerMunicipalityAdmin->value => Role::ReviewerMunicipalityAdmin->getLabel(),
                        Role::MunicipalityAdmin->value => Role::MunicipalityAdmin->getLabel(),
                    ])
                    ->selectablePlaceholder(false)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('municipality/resources/municipality_admin.columns.name.label'))
                    ->description(fn (User $record): string => $record->email)
                    ->searchable(),
                SelectColumn::make('role')
                    ->label(__('municipality/resources/municipality_admin.columns.role.label'))
                    ->options([
                        Role::Reviewer->value => Role::Reviewer->getLabel(),
                        Role::MunicipalityAdmin->value => Role::MunicipalityAdmin->getLabel(),
                        Role::ReviewerMunicipalityAdmin->value => Role::ReviewerMunicipalityAdmin->getLabel(),
                    ])
                    ->selectablePlaceholder(false)
                    ->afterStateUpdated(function () {
                        Notification::make()
                            ->title(__('municipality/resources/municipality_admin.columns.role.notification'))
                            ->success()
                            ->send();
                    }),
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
        /** @phpstan-ignore-next-line */
        return parent::getEloquentQuery()->admins();
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
