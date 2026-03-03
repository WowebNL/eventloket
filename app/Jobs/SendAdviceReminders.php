<?php

namespace App\Jobs;

use App\Enums\AdviceStatus;
use App\Models\Threads\AdviceThread;
use App\Models\Users\AdvisorUser;
use App\Notifications\AdviceReminder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendAdviceReminders implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $reminderDays = [5, 3, 0];

        foreach ($reminderDays as $daysBeforeDue) {
            $threads = AdviceThread::whereNotNull('advice_due_at')
                ->where('advice_status', AdviceStatus::Asked)
                ->whereDate('advice_due_at', now()->addDays($daysBeforeDue))
                ->get();

            /** @var AdviceThread $thread */
            foreach ($threads as $thread) {
                // Notify assigned users if any, otherwise notify advisory admin users
                $usersToNotify = $thread->assignedUsers->count()
                    ? $thread->assignedUsers
                    : $thread->advisory->adminUsers;

                /** @var AdvisorUser $user */
                foreach ($usersToNotify as $user) {
                    $user->notify(new AdviceReminder($thread, $daysBeforeDue));
                }
            }
        }
    }
}
