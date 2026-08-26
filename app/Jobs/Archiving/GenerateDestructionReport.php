<?php

namespace App\Jobs\Archiving;

use App\Enums\DestructionItemStatus;
use App\Models\Archiving\DestructionList;
use App\Models\Archiving\DestructionListItem;
use App\Models\Archiving\DestructionReport;
use App\Models\Municipality;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Writes the destruction report for a completed list.
 *
 * Safe to run more than once: the report row is created once per list
 * (unique on destruction_list_id) and the PDF is (re)rendered from that row,
 * so a run that died halfway is repaired by simply running it again.
 */
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
        $list = DestructionList::with('items')->find($this->destructionListId);

        if (! $list) {
            return;
        }

        $this->storePdf($this->report($list));
    }

    /**
     * The report row of this list, created on the first run. The row and the
     * reference from the list are written in one transaction, so the list can
     * never point at a report that does not exist and vice versa.
     */
    private function report(DestructionList $list): DestructionReport
    {
        return DB::transaction(function () use ($list): DestructionReport {
            // Locking the municipality serialises the batch numbering: the
            // read of the last issued number and the insert that follows it
            // cannot interleave with another list finalising right now.
            $municipality = Municipality::whereKey($list->municipality_id)->lockForUpdate()->firstOrFail();

            $report = DestructionReport::firstOrCreate(
                ['destruction_list_id' => $list->id],
                $this->attributes($list, $municipality),
            );

            $list->update(['destruction_report_id' => $report->id]);

            return $report;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(DestructionList $list, Municipality $municipality): array
    {
        $items = $list->items;

        return [
            'municipality_id' => $list->municipality_id,
            'destruction_list_id' => $list->id,
            'batch_number' => DestructionReport::nextBatchNumber($municipality),
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
        ];
    }

    /**
     * The PDF is rendered from the stored report, never from the list, so a
     * regenerated PDF is identical to the one that was issued originally.
     */
    private function storePdf(DestructionReport $report): void
    {
        $path = "archief/rapporten/{$report->batch_number}.pdf";

        Storage::disk(config('archiving.report_disk'))->put(
            $path,
            Pdf::loadView('pdf.destruction-report', ['report' => $report])->output(),
        );

        $report->update(['pdf_path' => $path]);
    }
}
