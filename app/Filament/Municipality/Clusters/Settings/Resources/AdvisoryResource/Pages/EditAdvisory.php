<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\AdvisoryResource\Pages;

use App\Filament\Municipality\Clusters\Settings\Resources\AdvisoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAdvisory extends EditRecord
{
    protected static string $resource = AdvisoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
