<?php

namespace App\Filament\Organiser\Clusters\Settings\Resources\UserResource\Pages;

use App\Filament\Actions\OrganiserUser\InviteAction;
use App\Filament\Organiser\Clusters\Settings;
use App\Filament\Organiser\Clusters\Settings\Resources\OrganiserUserResource;
use Filament\Resources\Pages\ListRecords;

class ListOrganiserUsers extends ListRecords
{
    protected static ?string $cluster = Settings::class;

    protected static string $resource = OrganiserUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            InviteAction::make(),
        ];
    }
}
