<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\AdvisoryResource\Pages;

use App\Filament\Municipality\Clusters\Settings\Resources\AdvisoryResource;
use App\Models\Advisory;
use App\Models\Municipality;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateAdvisory extends CreateRecord
{
    protected static string $resource = AdvisoryResource::class;

    protected function afterCreate(): void
    {
        /** @var Advisory $record */
        $record = $this->getRecord();

        /** @var Municipality|null $tenant */
        $tenant = Filament::getTenant();
        // Ensure the current tenant municipality is attached
        if ($tenant) {
            $record->municipalities()->syncWithoutDetaching([$tenant->id]);
        }
    }
}
