<?php

namespace App\Filament\Admin\Resources\Organisations\Pages;

use App\Enums\OrganisationType;
use App\Filament\Admin\Resources\Organisations\OrganisationResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateOrganisation extends CreateRecord
{
    protected static string $resource = OrganisationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $data['type'] = OrganisationType::Business;

        return parent::handleRecordCreation($data);
    }
}
