<?php

namespace App\Filament\Municipality\Resources\ReviewerUserResource\Pages;

use App\Filament\Municipality\Resources\ReviewerUserResource;
use App\Filament\Shared\Resources\ReviewerUsers\Actions\ReviewerUserInviteAction;
use App\Filament\Shared\Resources\ReviewerUsers\Actions\ReviewerUserPendingInvitesAction;
use Filament\Resources\Pages\ListRecords;

class ListReviewerUsers extends ListRecords
{
    protected static string $resource = ReviewerUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ReviewerUserPendingInvitesAction::make(),
            ReviewerUserInviteAction::make(),
        ];
    }
}
