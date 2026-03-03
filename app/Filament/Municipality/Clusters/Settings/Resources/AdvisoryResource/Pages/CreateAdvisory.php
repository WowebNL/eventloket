<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\AdvisoryResource\Pages;

use App\Filament\Municipality\Clusters\Settings\Resources\AdvisoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdvisory extends CreateRecord
{
    protected static string $resource = AdvisoryResource::class;

    protected function afterCreate(): void
    {
        /** @var \App\Models\Advisory $record */
        $record = $this->getRecord();

        /** @var \App\Models\Municipality|null $tenant */
        $tenant = \Filament\Facades\Filament::getTenant();
        // Ensure the current tenant municipality is attached
        if ($tenant) {
            $record->municipalities()->syncWithoutDetaching([$tenant->id]);
        }
    }
}
