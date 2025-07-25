<?php

namespace App\Filament\Clusters\AdminSettings\Resources\AdvisoryResource\Pages;

use App\Enums\Role;
use App\Filament\Clusters\AdminSettings\Resources\AdvisoryResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateAdvisory extends CreateRecord
{
    protected static string $resource = AdvisoryResource::class;

    protected function afterCreate(): void
    {
        if (auth()->user()->role === Role::MunicipalityAdmin) {
            /** @var \App\Models\Advisory $advisory */
            $advisory = $this->record;

            /** @var \App\Models\Organisation $organisation */
            $organisation = Filament::getTenant();

            $advisory->municipalities()->attach($organisation->id);
        }
    }
}
