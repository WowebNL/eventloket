<?php

namespace App\Observers;

use App\Mail\NewOrganiserThreadMail;
use App\Models\Threads\OrganiserThread;
use Illuminate\Support\Facades\Mail;

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
            Mail::to($user->email)
                ->send(new NewOrganiserThreadMail($organiserThread, $user));
        }
    }
}
