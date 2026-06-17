<?php

namespace App\Notifications;

use App\Models\Zaak;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DocumentsZipReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Zaak $zaak,
        private readonly string $token,
        private readonly int $documentCount,
    ) {}

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(mixed $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('Download gereed'))
            ->body(trans_choice(
                '{1} 1 document is ingepakt en beschikbaar voor download.|[2,*] :count documenten zijn ingepakt en beschikbaar voor download.',
                $this->documentCount,
                ['count' => $this->documentCount],
            ))
            ->success()
            ->actions([
                Action::make('download')
                    ->label(__('Download'))
                    ->url(route('zaak.documents.zip', [
                        'zaak' => $this->zaak->id,
                        'token' => $this->token,
                    ]))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
