<?php

namespace App\Filament\Organiser\Clusters\Settings\Resources\OrganiserUserResource\Pages;

use App\Filament\Organiser\Clusters\Settings;
use App\Filament\Organiser\Clusters\Settings\Resources\OrganiserUserResource;
use App\Filament\Shared\Resources\OrganiserUsers\Actions\OrganiserUserInviteAction;
use App\Filament\Shared\Resources\OrganiserUsers\Actions\OrganiserUserPendingInvitesAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListOrganiserUsers extends ListRecords
{
    protected static ?string $cluster = Settings::class;

    protected static string $resource = OrganiserUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            OrganiserUserPendingInvitesAction::make()
                ->widgetRecord(Filament::getTenant()),
            OrganiserUserInviteAction::make(),
        ];
    }
}
