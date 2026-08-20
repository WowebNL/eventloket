<?php

namespace App\Jobs\Archiving;

use App\Enums\DestructionListStatus;
use App\Models\Archiving\DestructionList;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FinalizeDestructionList implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(private int $destructionListId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $list = DestructionList::find($this->destructionListId);

        if (! $list || $list->status !== DestructionListStatus::Deleting) {
            return;
        }

        if ($list->hasFailedItems()) {
            $list->transitionTo(DestructionListStatus::Failed);

            return;
        }

        $list->transitionTo(DestructionListStatus::Deleted);

        // The report is only generated once, after a fully completed destruction.
        if (! $list->destruction_report_id) {
            GenerateDestructionReport::dispatch($list->id);
        }
    }
}
