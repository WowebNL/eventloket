<?php

namespace App\Filament\Shared\Resources\ReviewerUsers\Actions;

use App\Filament\Shared\Actions\PendingInvitesAction;
use App\Filament\Shared\Resources\ReviewerUsers\Widgets\PendingReviewerUserInvitesWidget;

class ReviewerUserPendingInvitesAction
{
    public static function make(): PendingInvitesAction
    {
        return PendingInvitesAction::make()
            ->modalHeading(__('municipality/resources/user.widgets.pending_invites.heading'))
            ->widget(PendingReviewerUserInvitesWidget::class);
    }
}
