<?php

namespace App\Filament\Admin\Resources\AdvisoryResource\RelationManagers;

use App\Enums\AdvisoryRole;
use App\Filament\Shared\Resources\AdvisorUsers\Actions\AdvisorUserInviteAction;
use App\Filament\Shared\Resources\AdvisorUsers\Actions\AdvisorUserPendingInvitesAction;
use App\Filament\Shared\Resources\AdvisorUsers\Schemas\AdvisorUserForm;
use App\Filament\Shared\Resources\AdvisorUsers\Tables\AdvisorUserTable;
use App\Models\Advisory;
use Filament\Actions\AttachAction;
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
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->multiple()
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'role' => AdvisoryRole::Member,
                    ]),
                AdvisorUserPendingInvitesAction::make()
                    ->widgetRecord($this->ownerRecord),
                AdvisorUserInviteAction::make(advisory: $advisory),
            ]);
    }
}
