<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\AdvisoryResource\Pages;

use App\Filament\Municipality\Clusters\Settings\Resources\AdvisoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdvisories extends ListRecords
{
    protected static string $resource = AdvisoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
