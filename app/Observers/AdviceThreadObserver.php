<?php

namespace App\Observers;

use App\Mail\NewAdviceThreadMail;
use App\Models\Threads\AdviceThread;
use App\Models\Users\AdvisorUser;
use Illuminate\Support\Facades\Mail;

class AdviceThreadObserver
{
    /**
     * Handle the AdviceThread "created" event.
     */
    public function created(AdviceThread $adviceThread): void
    {
        /** @var AdvisorUser $advisorUser */
        foreach ($adviceThread->advisory->users as $advisorUser) {
            Mail::to($advisorUser->email)
                ->send(new NewAdviceThreadMail($adviceThread));
        }
    }
}
