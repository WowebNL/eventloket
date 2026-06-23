<?php

namespace App\Jobs;

use App\EventForm\Persistence\DraftStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CleanupExpiredEventFormDrafts implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(DraftStore $draftStore): void
    {
        $deleted = $draftStore->pruneExpired();

        Log::info("Deleted {$deleted} expired event form drafts");
    }
}
