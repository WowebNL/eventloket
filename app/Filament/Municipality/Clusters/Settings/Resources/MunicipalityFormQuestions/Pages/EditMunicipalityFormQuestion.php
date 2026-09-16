<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityFormQuestions\Pages;

use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityFormQuestions\MunicipalityFormQuestionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMunicipalityFormQuestion extends EditRecord
{
    protected static string $resource = MunicipalityFormQuestionResource::class;

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
