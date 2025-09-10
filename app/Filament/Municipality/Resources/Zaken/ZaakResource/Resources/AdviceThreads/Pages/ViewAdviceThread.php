<?php

namespace App\Filament\Municipality\Resources\Zaken\ZaakResource\Resources\AdviceThreads\Pages;

use App\Enums\AdviceStatus;
use App\Filament\Municipality\Resources\Zaken\ZaakResource\Resources\AdviceThreads\AdviceThreadResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAdviceThread extends ViewRecord
{
    protected static string $resource = AdviceThreadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('markNeedsMoreInfo')
                ->label('More info needed')
                ->action(fn () => $this->record->update(['status' => AdviceStatus::NeedsMoreInfo])),
            Action::make('approve')
                ->label('Approve')
                ->color('success')
                ->action(fn () => $this->record->update(['status' => AdviceStatus::Approved])),

        ];
    }
}
