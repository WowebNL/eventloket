<?php

namespace App\Observers;

use App\Enums\ThreadType;
use App\Models\Message;
use App\Models\Thread;
use App\Notifications\NewAdviceThreadMessage;
use App\Notifications\NewOrganiserThreadMessage;

class MessageObserver
{
    /**
     * Handle the Message "created" event.
     */
    public function created(Message $message): void
    {
        $usersToNotify = $message->thread->getParticipants()
            ->where('id', '!=', $message->user_id);

        if ($usersToNotify->isEmpty()) {
            return;
        }

        // To add in the future: Check if the user wants to receive notifications for this thread.

        $message->unreadByUsers()->attach($usersToNotify->pluck('id'));

        // If this is the first message in the thread, and it's created by the creator of the thread, don't send a notification.
        if ($message->thread->messages->count() === 1 && $message->user_id === $message->thread->created_by) {
            return;
        }

        $mailableClass = $this->getMailableClassForThreadType($message->thread->type);

        foreach ($usersToNotify as $user) {
            $user->notify(new $mailableClass($message));
        }
    }

    private function getMailableClassForThreadType(ThreadType $threadType): string
    {
        return match ($threadType) {
            ThreadType::Advice => NewAdviceThreadMessage::class,
            ThreadType::Organiser => NewOrganiserThreadMessage::class,
        };
    }
}
