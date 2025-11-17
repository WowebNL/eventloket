<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\DefaultAdviceQuestions\Pages;

use App\Filament\Municipality\Clusters\Settings\Resources\DefaultAdviceQuestions\DefaultAdviceQuestionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDefaultAdviceQuestions extends ListRecords
{
    protected static string $resource = DefaultAdviceQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
