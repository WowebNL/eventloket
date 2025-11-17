<?php

namespace App\Observers;

use App\Enums\AdviceStatus;
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
        if ($adviceThread->advice_status !== AdviceStatus::Concept) {
            /** @var AdvisorUser $advisorUser */
            foreach ($adviceThread->advisory->adminUsers as $advisorUser) {
                $advisorUser->notify(new NewAdviceThread($adviceThread));
            }
        }
    }

    /**
     * Handle the AdviceThread "created" event.
     */
    public function updated(AdviceThread $adviceThread): void
    {
        if ($adviceThread->isDirty('advice_status') && $adviceThread->advice_status === AdviceStatus::Asked) {
            /** @var AdvisorUser $advisorUser */
            foreach ($adviceThread->advisory->adminUsers as $advisorUser) {
                $advisorUser->notify(new NewAdviceThread($adviceThread));
            }
        }
    }
}
