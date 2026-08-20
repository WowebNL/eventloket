<?php

namespace App\Jobs\Archiving;

use App\Enums\DestructionItemStatus;
use App\Models\Archiving\DestructionList;
use App\Models\Archiving\DestructionListItem;
use App\Models\Archiving\DestructionReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class GenerateDestructionReport implements ShouldQueue
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
        $list = DestructionList::with(['items', 'municipality'])->find($this->destructionListId);

        if (! $list || $list->destruction_report_id) {
            return;
        }

        $items = $list->items;

        $report = DestructionReport::create([
            'municipality_id' => $list->municipality_id,
            'destruction_list_id' => $list->id,
            'batch_number' => DestructionReport::nextBatchNumber($list->municipality),
            'coordinator_name' => $list->coordinator_name ?? '',
            'coordinator_function' => $list->coordinator_function,
            'coordinator_user_id' => $list->created_by_user_id,
            'destruction_method' => $list->destruction_method ?? '',
            'destruction_date' => now(),
            'items' => $items->map(fn (DestructionListItem $item) => $item->toReportEntry())->values()->all(),
            'total_count' => $items->count(),
            'deleted_count' => $items->where('status', DestructionItemStatus::Deleted)->count(),
            'failed_count' => $items->where('status', DestructionItemStatus::Failed)->count(),
            'skipped_count' => $items->where('status', DestructionItemStatus::Skipped)->count(),
        ]);

        $pdfPath = "archief/rapporten/{$report->batch_number}.pdf";

        Storage::disk('local')->put(
            $pdfPath,
            Pdf::loadView('pdf.destruction-report', ['report' => $report])->output(),
        );

        $report->update(['pdf_path' => $pdfPath]);

        $list->update(['destruction_report_id' => $report->id]);
    }
}
