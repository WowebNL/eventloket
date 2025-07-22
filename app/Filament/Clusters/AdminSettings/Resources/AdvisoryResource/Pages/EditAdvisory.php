<?php

namespace App\Filament\Clusters\AdminSettings\Resources\AdvisoryResource\Pages;

use App\Filament\Clusters\AdminSettings\Resources\AdvisoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAdvisory extends EditRecord
{
    protected static string $resource = AdvisoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
