<?php

namespace App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\Pages;

use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\AdviceThreadResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewAdviceThread extends ViewRecord
{
    protected static string $resource = AdviceThreadResource::class;

    protected $listeners = ['thread-updated' => '$refresh'];

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @phpstan-ignore-next-line */
        auth()->user()->unreadMessages()->detach($this->record->messages()->pluck('id'));
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getRecordTitle();
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
