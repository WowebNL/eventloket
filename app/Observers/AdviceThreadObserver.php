<?php

namespace App\Observers;

use App\Models\Threads\AdviceThread;
use App\Models\Users\AdvisorUser;
use App\Notifications\NewAdviceThread;

class AdviceThreadObserver
{
    /**
     * Handle the AdviceThread "created" event.
     */
    public function created(AdviceThread $adviceThread): void
    {
        /** @var AdvisorUser $advisorUser */
        foreach ($adviceThread->advisory->adminUsers as $advisorUser) {
            $advisorUser->notify(new NewAdviceThread($adviceThread));
        }
    }
}
