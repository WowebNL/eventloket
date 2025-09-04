<?php

namespace App\Filament\Admin\Resources\Organisations\RelationManagers;

use App\Filament\Shared\Resources\OrganiserUsers\Actions\OrganiserUserInviteAction;
use App\Filament\Shared\Resources\OrganiserUsers\Actions\OrganiserUserPendingInvitesAction;
use App\Filament\Shared\Resources\OrganiserUsers\Schemas\OrganiserUserForm;
use App\Filament\Shared\Resources\OrganiserUsers\Tables\OrganiserUserTable;
use App\Models\Organisation;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin/resources/organisation.user.plural_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin/resources/organisation.user.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/resources/organisation.user.plural_label');
    }

    public function form(Schema $schema): Schema
    {
        return OrganiserUserForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        /** @var Organisation $organisation */
        $organisation = $this->ownerRecord;

        return OrganiserUserTable::configure($table, $organisation)
            ->headerActions([
                OrganiserUserPendingInvitesAction::make()
                    ->widgetRecord($this->ownerRecord),
                OrganiserUserInviteAction::make(organisation: $organisation),
            ]);
    }
}
