<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\ArchiveUserResource\Pages;

use App\Filament\Municipality\Clusters\Settings\Resources\ArchiveUserResource;
use App\Filament\Municipality\Clusters\Settings\Resources\ArchiveUserResource\Actions\ArchiveUserInviteAction;
use Filament\Resources\Pages\ListRecords;

class ListArchiveUsers extends ListRecords
{
    protected static string $resource = ArchiveUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ArchiveUserInviteAction::make(),
        ];
    }
}
