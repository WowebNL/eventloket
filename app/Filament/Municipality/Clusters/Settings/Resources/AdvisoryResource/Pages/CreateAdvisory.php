<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\AdvisoryResource\Pages;

use App\Filament\Municipality\Clusters\Settings\Resources\AdvisoryResource;
use App\Models\Advisory;
use App\Models\Organisation;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateAdvisory extends CreateRecord
{
    protected static string $resource = AdvisoryResource::class;

    protected function afterCreate(): void
    {
        /** @var Advisory $advisory */
        $advisory = $this->record;

        /** @var Organisation $organisation */
        $organisation = Filament::getTenant();

        $advisory->municipalities()->attach($organisation->id);
    }
}
