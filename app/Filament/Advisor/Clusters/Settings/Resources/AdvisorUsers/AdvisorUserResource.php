<?php

namespace App\Filament\Advisor\Clusters\Settings\Resources\AdvisorUsers;

use App\Enums\AdvisoryRole;
use App\Filament\Advisor\Clusters\Settings\Resources\AdvisorUsers\Pages\ListAdvisorUsers;
use App\Filament\Advisor\Clusters\Settings\SettingsCluster;
use App\Filament\Shared\Resources\AdvisorUsers\Actions\AdvisorUserInviteAction;
use App\Filament\Shared\Resources\AdvisorUsers\Actions\AdvisorUserPendingInvitesAction;
use App\Filament\Shared\Resources\AdvisorUsers\Schemas\AdvisorUserForm;
use App\Filament\Shared\Resources\AdvisorUsers\Tables\AdvisorUserTable;
use App\Models\Advisory;
use App\Models\Users\AdvisorUser;
use App\Models\Users\OrganiserUser;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AdvisorUserResource extends Resource
{
    protected static ?string $cluster = SettingsCluster::class;

    protected static ?string $model = AdvisorUser::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $tenantOwnershipRelationshipName = 'advisories';

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Access check for the resource
     * custom override because the resource is using the UserPolicy which is not correct for this resource
     * maybe refactor later by using a custom model for example OrganiserUser and related policy
     */
    public static function canAccess(): bool
    {
        /** @var Advisory $tenant */
        $tenant = Filament::getTenant();

        /** @var AdvisorUser $user */
        $user = auth()->user();

        return $user->canAccessAdvisory($tenant->id, AdvisoryRole::Admin);
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
        return AdvisorUserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        /** @var Advisory $tenant */
        $tenant = Filament::getTenant();

        return AdvisorUserTable::configure($table, $tenant)
            ->headerActions([
                AdvisorUserPendingInvitesAction::make()
                    ->widgetRecord($tenant),
                AdvisorUserInviteAction::make(advisory: $tenant),
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
            'index' => ListAdvisorUsers::route('/'),
        ];
    }
}
