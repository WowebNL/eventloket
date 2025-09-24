<?php

namespace App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\OrganiserThreads\Pages;

use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\OrganiserThreads\OrganiserThreadResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewOrganiserThread extends ViewRecord
{
    protected static string $resource = OrganiserThreadResource::class;

    protected $listeners = ['thread-updated' => '$refresh'];

    public function getTitle(): string|Htmlable
    {
        return $this->getRecordTitle();
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
