<?php

namespace App\Jobs\Archiving;

use App\Enums\DestructionItemStatus;
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

        $this->failUnfinishedItems($list);

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

    /**
     * An item that is still pending or processing when the batch is done never
     * reached a terminal state: its job was dropped, or it stopped after its
     * last attempt. A destruction report may only be issued for a list where
     * every item is accounted for, so these count as failures and the list
     * stays retryable.
     */
    private function failUnfinishedItems(DestructionList $list): void
    {
        $list->items()
            ->whereIn('status', [DestructionItemStatus::Pending, DestructionItemStatus::Processing])
            ->update([
                'status' => DestructionItemStatus::Failed,
                'failure_reason' => 'De vernietiging van deze zaak is niet afgerond',
            ]);
    }
}
