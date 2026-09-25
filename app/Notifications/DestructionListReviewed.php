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

class DestructionListReviewed extends BaseNotification
{
    private string $listName;

    private string $viewUrl;

    private string $type;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        protected DestructionList $destructionList,
        bool $approved,
    ) {
        $this->listName = $destructionList->name;
        $this->viewUrl = DestructionListResource::getUrl('view', ['record' => $destructionList], panel: 'municipality', tenant: $destructionList->municipality);
        $this->type = $approved ? 'approved' : 'changes_requested';
    }

    public static function getLabel(): string|Htmlable|null
    {
        return __('notification/destruction-list-reviewed.label');
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__("notification/destruction-list-reviewed.mail.subject.{$this->type}", ['list' => $this->listName]))
            ->greeting(__("notification/destruction-list-reviewed.mail.greeting.{$this->type}"))
            ->line(__("notification/destruction-list-reviewed.mail.body.{$this->type}", ['list' => $this->listName]))
            ->action(__('notification/destruction-list-reviewed.mail.button'), $this->viewUrl);
    }

    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__("notification/destruction-list-reviewed.database.title.{$this->type}", ['list' => $this->listName]))
            ->body(__("notification/destruction-list-reviewed.database.body.{$this->type}", ['list' => $this->listName]))
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
