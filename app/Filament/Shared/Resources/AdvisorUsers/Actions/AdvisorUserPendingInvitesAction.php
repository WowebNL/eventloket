<?php

namespace App\Filament\Shared\Resources\AdvisorUsers\Actions;

use App\Filament\Shared\Actions\PendingInvitesAction;
use App\Filament\Shared\Resources\AdvisorUsers\Widgets\PendingAdvisoryInvitesWidget;

class AdvisorUserPendingInvitesAction
{
    public static function make(): PendingInvitesAction
    {
        return PendingInvitesAction::make()
            ->modalHeading(__('admin/resources/organisation.widgets.pending_invites.heading'))
            ->widget(PendingAdvisoryInvitesWidget::class);
    }
}
