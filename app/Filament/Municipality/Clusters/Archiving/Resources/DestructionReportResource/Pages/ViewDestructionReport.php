<?php

namespace App\Filament\Municipality\Clusters\Archiving\Resources\DestructionReportResource\Pages;

use App\Filament\Municipality\Clusters\Archiving\Actions\RegenerateDestructionReportAction;
use App\Filament\Municipality\Clusters\Archiving\Resources\DestructionReportResource;
use Filament\Resources\Pages\ViewRecord;

class ViewDestructionReport extends ViewRecord
{
    protected static string $resource = DestructionReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DestructionReportResource::getDownloadPdfAction(),
            RegenerateDestructionReportAction::make(),
        ];
    }
}
