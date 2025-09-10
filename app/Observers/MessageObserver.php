<?php

namespace App\Observers;

use App\Models\Message;

class MessageObserver
{
    /**
     * Handle the Message "created" event.
     */
    public function created(Message $message): void
    {
        // TODO: Create a unread_messages entry for all users that are related to this message.
        // TODO: Sent some mails?
    }
}
