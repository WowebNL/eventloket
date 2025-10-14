<?php

namespace App\Observers;

use App\Models\Threads\OrganiserThread;
use App\Notifications\NewOrganiserThread;

class OrganiserThreadObserver
{
    /**
     * Handle the OrganiserThread "created" event.
     */
    public function created(OrganiserThread $organiserThread): void
    {
        $usersToNotify = $organiserThread->getParticipants()
            ->where('id', '!=', $organiserThread->created_by);

        foreach ($usersToNotify as $user) {
            $user->notify(new NewOrganiserThread($organiserThread));
        }
    }
}
