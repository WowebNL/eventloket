<?php

namespace App\Filament\Shared\Resources\ReportQuestions\Concerns;

use App\Models\ReportQuestion;
use Illuminate\Support\Facades\DB;

trait SafelyReordersReportQuestions
{
    /**
     * Override Filament's default bulk CASE WHEN update to avoid unique
     * constraint violations on (municipality_id, order). Works on both
     * PostgreSQL and MySQL.
     *
     * Strategy: first shift all affected orders to a temporary high value
     * (no conflicts within the same municipality), then set the final
     * values one by one.
     *
     * @param  array<int|string>  $order
     */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        if (! $this->getTable()->isReorderable()) {
            return;
        }

        $this->getTable()->callBeforeReordering($order);

        DB::transaction(function () use ($order): void {
            $ids = array_values($order);

            // Pass 1: shift all to high values so none of the 1-10 slots are occupied.
            // Max 10 records with order values 1–10; adding 200 safely stays within
            // the unsignedTinyInteger range (max 255).
            ReportQuestion::whereIn('id', $ids)->increment('order', 200);

            // Pass 2: write the final positions. No conflicts because all targeted
            // records are currently sitting at 201–210.
            foreach ($order as $index => $id) {
                ReportQuestion::where('id', $id)->update(['order' => $index + 1]);
            }
        });

        $this->getTable()->callAfterReordering($order);
    }
}
