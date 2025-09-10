<?php

namespace App\Filament\Municipality\Resources\Zaken\ZaakResource\Resources\AdviceThreads\Pages;

use App\Filament\Municipality\Resources\Zaken\ZaakResource\Resources\AdviceThreads\AdviceThreadResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAdviceThread extends EditRecord
{
    protected static string $resource = AdviceThreadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
