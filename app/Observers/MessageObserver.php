<?php

namespace App\Observers;

use App\Enums\ThreadType;
use App\Mail\NewAdviceThreadMessageMail;
use App\Mail\NewOrganiserThreadMessageMail;
use App\Models\Message;
use App\Models\Thread;
use App\Models\Threads\AdviceThread;
use App\Models\Threads\OrganiserThread;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class MessageObserver
{
    /**
     * Handle the Message "created" event.
     */
    public function created(Message $message): void
    {
        $usersToNotify = $this->getThreadParticipants($message->thread)
            ->where('id', '!=', $message->user_id);

        if ($usersToNotify->isEmpty()) {
            return;
        }

        // To add in the future: Check if the user wants to receive notifications for this thread.

        $message->unreadByUsers()->attach($usersToNotify->pluck('id'));

        $mailableClass = $this->getMailableClassForThreadType($message->thread->type);

        // TODO: Is dit het eerste bericht in de thread? Stuur dan geen mails.

        foreach ($usersToNotify as $user) {
            Mail::to($user->email)->send(new $mailableClass($message));
        }
    }

    private function getThreadParticipants(Thread $thread): Collection
    {
        $threadParticipants = match (get_class($thread)) {
            AdviceThread::class => $thread->advisory->users,
            OrganiserThread::class => $thread->zaak->organisation->users,
            default => collect(),
        };

        $municipalityReviewerUsers = $thread->zaak->municipality->allReviewerUsers;

        return $threadParticipants->merge($municipalityReviewerUsers);
    }

    private function getMailableClassForThreadType(ThreadType $threadType): string
    {
        return match ($threadType) {
            ThreadType::Advice => NewAdviceThreadMessageMail::class,
            ThreadType::Organiser => NewOrganiserThreadMessageMail::class,
        };
    }
}
