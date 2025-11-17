<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\DefaultAdviceQuestions\Pages;

use App\Filament\Municipality\Clusters\Settings\Resources\DefaultAdviceQuestions\DefaultAdviceQuestionResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateDefaultAdviceQuestion extends CreateRecord
{
    protected static string $resource = DefaultAdviceQuestionResource::class;
}
