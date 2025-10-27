<?php

namespace App\Filament\Admin\Resources\AdvisoryResource\RelationManagers;

use App\Filament\Shared\Resources\AdvisorUsers\Actions\AdvisorUserInviteAction;
use App\Filament\Shared\Resources\AdvisorUsers\Actions\AdvisorUserPendingInvitesAction;
use App\Filament\Shared\Resources\AdvisorUsers\Schemas\AdvisorUserForm;
use App\Filament\Shared\Resources\AdvisorUsers\Tables\AdvisorUserTable;
use App\Models\Advisory;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin/resources/advisory.user.plural_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin/resources/advisory.user.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/resources/advisory.user.plural_label');
    }

    public function form(Schema $schema): Schema
    {
        return AdvisorUserForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        /** @var Advisory $advisory */
        $advisory = $this->ownerRecord;

        return AdvisorUserTable::configure($table, $advisory)
            ->headerActions([
                AdvisorUserPendingInvitesAction::make()
                    ->widgetRecord($this->ownerRecord),
                AdvisorUserInviteAction::make(advisory: $advisory),
            ]);
    }
}
