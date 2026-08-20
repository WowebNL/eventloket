<?php

namespace App\Notifications;

use App\Filament\Municipality\Clusters\Archiving\Resources\DestructionListResource;
use App\Models\Archiving\DestructionList;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;

class DestructionListReadyForReview extends BaseNotification
{
    private string $listName;

    private string $municipalityName;

    private string $viewUrl;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        protected DestructionList $destructionList,
    ) {
        $this->listName = $destructionList->name;
        $this->municipalityName = $destructionList->municipality->name;
        $this->viewUrl = DestructionListResource::getUrl('view', ['record' => $destructionList], panel: 'municipality', tenant: $destructionList->municipality);
    }

    public static function getLabel(): string|Htmlable|null
    {
        return __('notification/destruction-list-ready-for-review.label');
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notification/destruction-list-ready-for-review.mail.subject', ['list' => $this->listName]))
            ->greeting(__('notification/destruction-list-ready-for-review.mail.greeting'))
            ->line(__('notification/destruction-list-ready-for-review.mail.body', ['list' => $this->listName, 'municipality' => $this->municipalityName]))
            ->action(__('notification/destruction-list-ready-for-review.mail.button'), $this->viewUrl);
    }

    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('notification/destruction-list-ready-for-review.database.title', ['list' => $this->listName]))
            ->body(__('notification/destruction-list-ready-for-review.database.body', ['list' => $this->listName, 'municipality' => $this->municipalityName]))
            ->actions([
                Action::make('view')
                    ->label(__('View'))
                    ->url($this->viewUrl)
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    public function logSubject(): Model
    {
        return $this->destructionList;
    }
}
