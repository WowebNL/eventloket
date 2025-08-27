<?php

namespace App\Filament\Organiser\Clusters\Settings\Resources\UserResource\Pages;

use App\Filament\Actions\OrganiserUser\InviteAction;
use App\Filament\Actions\PendingInvitesAction;
use App\Filament\Organiser\Clusters\Settings;
use App\Filament\Organiser\Clusters\Settings\Resources\OrganiserUserResource;
use App\Filament\Organiser\Clusters\Settings\Resources\OrganiserUserResource\Widgets\PendingOrganisationInvitesWidget;
use Filament\Resources\Pages\ListRecords;

class ListOrganiserUsers extends ListRecords
{
    protected static ?string $cluster = Settings::class;

    protected static string $resource = OrganiserUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PendingInvitesAction::make()
                ->widget(PendingOrganisationInvitesWidget::class),
            InviteAction::make(),
        ];
    }
}
