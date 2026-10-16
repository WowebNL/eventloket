<?php

namespace App\Jobs\Archiving;

use App\Enums\DestructionItemStatus;
use App\Enums\DestructionListStatus;
use App\Models\Archiving\DestructionList;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;

class StartDestructionListDeletion implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(private DestructionList $destructionList) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $list = $this->destructionList->refresh();

        if ($list->status !== DestructionListStatus::Deleting) {
            return;
        }

        // Failed items are included so a retry resumes where it left off.
        $items = $list->items()
            ->whereIn('status', [DestructionItemStatus::Pending, DestructionItemStatus::Processing, DestructionItemStatus::Failed])
            ->get();

        $listId = $list->id;

        if ($items->isEmpty()) {
            FinalizeDestructionList::dispatch($listId);

            return;
        }

        Bus::batch($items->map(fn ($item) => new ExecuteZaakDestruction($item))->all())
            ->name("destruction-list-{$listId}")
            ->allowFailures()
            ->finally(function () use ($listId) {
                FinalizeDestructionList::dispatch($listId);
            })
            ->dispatch();
    }
}
