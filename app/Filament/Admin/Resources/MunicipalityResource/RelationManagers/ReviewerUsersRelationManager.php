<?php

namespace App\Filament\Admin\Resources\MunicipalityResource\RelationManagers;

use App\Filament\Shared\Resources\ReviewerUsers\Actions\ReviewerUserInviteAction;
use App\Filament\Shared\Resources\ReviewerUsers\Actions\ReviewerUserPendingInvitesAction;
use App\Filament\Shared\Resources\ReviewerUsers\Schemas\ReviewerUserForm;
use App\Filament\Shared\Resources\ReviewerUsers\Tables\ReviewerUsersTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ReviewerUsersRelationManager extends RelationManager
{
    protected static string $relationship = 'allReviewerUsers';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('resources/reviewer_user.plural_label');
    }

    public function form(Schema $schema): Schema
    {
        return ReviewerUserForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        /** @var \App\Models\Municipality $municipality */
        $municipality = $this->ownerRecord;

        return ReviewerUsersTable::configure($table)
            ->headerActions([
                ReviewerUserPendingInvitesAction::make()
                    ->widgetRecord($municipality),
                ReviewerUserInviteAction::make($municipality),
            ]);
    }
}
