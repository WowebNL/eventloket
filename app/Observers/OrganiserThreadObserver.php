<?php

namespace App\Observers;

use App\Models\Threads\OrganiserThread;

class OrganiserThreadObserver
{
    /**
     * Handle the OrganiserThread "created" event.
     */
    public function created(OrganiserThread $organiserThread): void
    {
        // TODO: Mails sturen.
        // TODO: Alle message unread dingen aanmaken.
    }
}
