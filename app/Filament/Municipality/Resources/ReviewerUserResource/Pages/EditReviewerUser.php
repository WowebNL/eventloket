<?php

namespace App\Filament\Municipality\Resources\ReviewerUserResource\Pages;

use App\Filament\Municipality\Resources\ReviewerUserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReviewerUser extends EditRecord
{
    protected static string $resource = ReviewerUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
