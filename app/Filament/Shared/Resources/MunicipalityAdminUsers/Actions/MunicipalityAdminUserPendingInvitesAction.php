<?php

namespace App\Filament\Shared\Resources\MunicipalityAdminUsers\Actions;

use App\Filament\Shared\Actions\PendingInvitesAction;
use App\Filament\Shared\Resources\MunicipalityAdminUsers\Widgets\PendingMunicipalityAdminUserInvitesWidget;

class MunicipalityAdminUserPendingInvitesAction
{
    public static function make(): PendingInvitesAction
    {
        return PendingInvitesAction::make()
            ->modalHeading(__('municipality/resources/municipality_admin.widgets.pending_invites.heading'))
            ->widget(PendingMunicipalityAdminUserInvitesWidget::class);
    }
}
