<?php

namespace App\Filament\Admin\Resources\MunicipalityResource\RelationManagers;

use App\Filament\Shared\Resources\MunicipalityAdminUsers\Actions\MunicipalityAdminUserInviteAction;
use App\Filament\Shared\Resources\MunicipalityAdminUsers\Actions\MunicipalityAdminUserPendingInvitesAction;
use App\Filament\Shared\Resources\MunicipalityAdminUsers\Schemas\MunicipalityAdminUserForm;
use App\Filament\Shared\Resources\MunicipalityAdminUsers\Tables\MunicipalityAdminUserTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MunicipalityAdminUsersRelationManager extends RelationManager
{
    protected static string $relationship = 'municipalityAdminUsers';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('resources/municipality_admin_user.plural_label');
    }

    public function form(Schema $schema): Schema
    {
        return MunicipalityAdminUserForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return MunicipalityAdminUserTable::configure($table)
            ->headerActions([
                MunicipalityAdminUserPendingInvitesAction::make()
                    ->widgetRecord($this->ownerRecord),
                MunicipalityAdminUserInviteAction::make(),
            ]);
    }
}
