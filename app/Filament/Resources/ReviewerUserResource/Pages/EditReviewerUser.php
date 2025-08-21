<?php

namespace App\Filament\Resources\ReviewerUserResource\Pages;

use App\Filament\Resources\ReviewerUserResource;
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
