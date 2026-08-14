<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityFormQuestions\Pages;

use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityFormQuestions\MunicipalityFormQuestionResource;
use App\Models\Municipality;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateMunicipalityFormQuestion extends CreateRecord
{
    protected static string $resource = MunicipalityFormQuestionResource::class;

    /**
     * Bind the question to the current tenant explicitly. Filament's tenancy
     * observer does the same, but only once the panel's middleware has run;
     * setting it here keeps the create page correct in every context and
     * matches the NOT NULL column.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = Filament::getTenant();

        if ($tenant instanceof Municipality) {
            $data['municipality_id'] = $tenant->getKey();
        }

        return $data;
    }
}
