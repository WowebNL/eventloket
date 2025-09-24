<?php

namespace App\Observers;

use App\Enums\ThreadType;
use App\Mail\NewAdviceThreadMessageMail;
use App\Mail\NewOrganiserThreadMessageMail;
use App\Models\Message;
use App\Models\Thread;
use Illuminate\Support\Facades\Mail;

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
            Mail::to($user->email)->send(new $mailableClass($message, $user));
        }
    }

    private function getMailableClassForThreadType(ThreadType $threadType): string
    {
        return match ($threadType) {
            ThreadType::Advice => NewAdviceThreadMessageMail::class,
            ThreadType::Organiser => NewOrganiserThreadMessageMail::class,
        };
    }
}
