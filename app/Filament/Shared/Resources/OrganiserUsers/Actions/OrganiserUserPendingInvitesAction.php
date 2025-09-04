<?php

namespace App\Filament\Shared\Resources\OrganiserUsers\Actions;

use App\Filament\Shared\Actions\PendingInvitesAction;
use App\Filament\Shared\Resources\OrganiserUsers\Widgets\PendingOrganisationInvitesWidget;

class OrganiserUserPendingInvitesAction
{
    public static function make(): PendingInvitesAction
    {
        return PendingInvitesAction::make()
            ->modalHeading(__('admin/resources/organisation.widgets.pending_invites.heading'))
            ->widget(PendingOrganisationInvitesWidget::class);
    }
}
