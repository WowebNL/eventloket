<?php

namespace App\Filament\Organiser\Clusters\Settings\Resources;

use App\Enums\OrganisationRole;
use App\Filament\Organiser\Clusters\Settings;
use App\Filament\Organiser\Clusters\Settings\Resources\UserResource\Pages\ListOrganiserUsers;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Users\OrganiserUser;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrganiserUserResource extends Resource
{
    protected static ?string $cluster = Settings::class;

    protected static ?string $model = OrganiserUser::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $tenantOwnershipRelationshipName = 'organisations';

    /**
     * Access check for the resource
     * custom override because the resource is using the UserPolicy which is not correct for this resource
     * maybe refactor later by using a custom model for example OrganiserUser and related policy
     */
    public static function canAccess(): bool
    {
        /** @var Organisation $tenant */
        $tenant = Filament::getTenant();

        /** @var OrganiserUser $user */
        $user = auth()->user();

        return $user->canAccessOrganisation($tenant->id, OrganisationRole::Admin);
    }

    public static function getModelLabel(): string
    {
        return __('organiser/resources/user.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('organiser/resources/user.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        /** @var Organisation $tenant */
        $tenant = Filament::getTenant();
        $columns = [
            TextColumn::make('name')
                ->label(__('organiser/resources/user.columns.name.label'))
                ->description(fn (OrganiserUser $record): string => $record->email)
                ->searchable(),

        ];

        if (self::userIsOrganisationAdmin(auth()->user())) {
            $columns[] =
                SelectColumn::make('organisations.role')
                    ->label(__('organiser/resources/user.columns.role.label'))
                    ->options(OrganisationRole::class)
                    ->getStateUsing(fn (OrganiserUser $record) => self::getUserRoleState($record))
                    ->updateStateUsing(fn (OrganiserUser $record, string $state) => $record->organisations()->updateExistingPivot($tenant->id, ['role' => $state]))
                    ->selectablePlaceholder(false)
                    ->disabled(fn (OrganiserUser $record) => $record->id === auth()->id());
        } else {
            $columns[] =
                TextColumn::make('status')
                    ->label(__('organiser/resources/user.columns.role.label'))
                    ->getStateUsing(fn (OrganiserUser $record) => self::getUserRoleState($record));
        }

        return $table
            ->columns($columns)
            ->filters([
                //
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
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
            'index' => ListOrganiserUsers::route('/'),
        ];
    }

    private static function userIsOrganisationAdmin(User $user): bool
    {
        /** @var Organisation $tenant */
        $tenant = Filament::getTenant();

        if ($user instanceof OrganiserUser) {
            return $user->canAccessOrganisation($tenant->id, OrganisationRole::Admin);
        }

        return false;
    }

    private static function getUserRoleState(OrganiserUser $user): OrganisationRole
    {
        /** @phpstan-ignore-next-line */
        $role = $user->organisations->firstWhere('id', Filament::getTenant()->id)?->pivot->role ?? OrganisationRole::Member->value;

        return OrganisationRole::from($role);
    }
}
