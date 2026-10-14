<?php

namespace App\Filament\Shared\Resources\MunicipalityFormQuestions\Concerns;

use App\Models\MunicipalityFormQuestion;
use Illuminate\Support\Facades\DB;

trait SafelyReordersMunicipalityFormQuestions
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

            // Pass 1: shift all to high values so none of the low slots are
            // occupied. A municipality is capped at 15 questions, so adding
            // 200 stays well within the unsignedTinyInteger range (max 255).
            MunicipalityFormQuestion::whereIn('id', $ids)->increment('order', 200);

            // Pass 2: write the final positions. No conflicts because all
            // targeted records are currently sitting at 201 and up.
            foreach ($order as $index => $id) {
                MunicipalityFormQuestion::where('id', $id)->update(['order' => $index + 1]);
            }
        });

        $this->getTable()->callAfterReordering($order);
    }
}
