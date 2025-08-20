<?php

namespace App\Filament\Clusters\AdminSettings\Resources\ApplicationResource\Pages;

use App\Filament\Clusters\AdminSettings\Resources\ApplicationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListApplications extends ListRecords
{
    protected static string $resource = ApplicationResource::class;

    public function getResourceInformation(): string
    {
        return __('admin/resources/application.resource_information');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
