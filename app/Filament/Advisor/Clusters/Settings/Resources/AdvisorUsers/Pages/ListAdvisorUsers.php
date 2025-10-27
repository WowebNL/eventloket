<?php

namespace App\Filament\Advisor\Clusters\Settings\Resources\AdvisorUsers\Pages;

use App\Filament\Advisor\Clusters\Settings\Resources\AdvisorUsers\AdvisorUserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdvisorUsers extends ListRecords
{
    protected static string $resource = AdvisorUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
