<?php

namespace App\Filament\Organiser\Clusters\Settings\Resources;

use App\Enums\OrganisationRole;
use App\Filament\Organiser\Clusters\Settings;
use App\Filament\Organiser\Clusters\Settings\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $cluster = Settings::class;

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $tenantOwnershipRelationshipName = 'organisations';

    /**
     * Access check for the resource
     * custom override because the resource is using the UserPolicy which is not correct for this resource
     * maybe refactor later by using a custom model for example OrganiserUser and related policy
     */
    public static function canAccess(): bool
    {
        /** @var \App\Models\Organisation $tenant */
        $tenant = Filament::getTenant();

        return auth()->user()->canAccessOrganisation($tenant->id, OrganisationRole::Admin);
    }

    public static function getModelLabel(): string
    {
        return __('organiser/resources/user.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('organiser/resources/user.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        /** @var \App\Models\Organisation $tenant */
        $tenant = Filament::getTenant();
        $columns = [
            Tables\Columns\TextColumn::make('name')
                ->label(__('organiser/resources/user.columns.name.label'))
                ->description(fn (User $record): string => $record->email)
                ->searchable(),

        ];

        if (self::userIsOrganisationAdmin(auth()->user())) {
            $columns[] =
                Tables\Columns\SelectColumn::make('organisations.role')
                    ->label(__('organiser/resources/user.columns.role.label'))
                    ->options(OrganisationRole::getOptions())
                    ->getStateUsing(fn (User $record) => self::getUserRoleState($record))
                    ->updateStateUsing(fn (User $record, string $state) => $record->organisations()->updateExistingPivot($tenant->id, ['role' => $state]))
                    ->selectablePlaceholder(false)
                    ->disabled(fn (User $record) => $record->id === auth()->id());
        } else {
            $columns[] =
                Tables\Columns\TextColumn::make('status')
                    ->label(__('organiser/resources/user.columns.role.label'))
                    ->getStateUsing(fn (User $record) => self::getUserRoleState($record));
        }

        return $table
            ->columns($columns)
            ->filters([
                //
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
        ];
    }

    private static function userIsOrganisationAdmin(User $user): bool
    {
        /** @var \App\Models\Organisation $tenant */
        $tenant = Filament::getTenant();

        return $user->canAccessOrganisation($tenant->id, OrganisationRole::Admin);
    }

    private static function getUserRoleState(User $user): OrganisationRole
    {
        /** @phpstan-ignore-next-line */
        $role = $user->organisations->firstWhere('id', Filament::getTenant()->id)?->pivot->role;

        return OrganisationRole::from($role);
    }
}
