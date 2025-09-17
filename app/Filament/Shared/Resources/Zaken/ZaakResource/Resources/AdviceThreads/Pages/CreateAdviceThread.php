<?php

namespace App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\Pages;

use App\Enums\AdviceStatus;
use App\Enums\ThreadType;
use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\AdviceThreadResource;
use App\Models\Message;
use Filament\Resources\Pages\CreateRecord;

class CreateAdviceThread extends CreateRecord
{
    protected static string $resource = AdviceThreadResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = ThreadType::Advice;
        $data['created_by'] = auth()->user()->id;

        $data['advice_status'] = AdviceStatus::Asked;

        return $data;
    }

    protected function afterCreate(): void
    {
        $formState = $this->form->getState();

        /** @var \App\Models\Thread $thread */
        $thread = $this->record;

        if ($formState['body'] != '<p></p>' && $formState['body'] != null) {
            Message::create([
                'thread_id' => $thread->id,
                'user_id' => auth()->user()->id,
                'body' => $formState['body'],
            ]);
        }
    }
}
