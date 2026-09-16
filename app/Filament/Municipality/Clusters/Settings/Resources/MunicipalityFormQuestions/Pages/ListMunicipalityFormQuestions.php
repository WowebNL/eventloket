<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityFormQuestions\Pages;

use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityFormQuestions\MunicipalityFormQuestionResource;
use App\Filament\Shared\Resources\MunicipalityFormQuestions\Concerns\SafelyReordersMunicipalityFormQuestions;
use Filament\Resources\Pages\ListRecords;

class ListMunicipalityFormQuestions extends ListRecords
{
    use SafelyReordersMunicipalityFormQuestions;

    protected static string $resource = MunicipalityFormQuestionResource::class;

    /**
     * The create button lives in the table's header actions (shared with the
     * admin panel's relation manager), so the page adds none of its own.
     *
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
