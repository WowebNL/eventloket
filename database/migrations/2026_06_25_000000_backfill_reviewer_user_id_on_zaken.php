<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill reviewer_user_id from the previous handled_status_set_by_user_id.
     *
     * Before #389 the working stock (and its navigation badges) was scoped by
     * handled_status_set_by_user_id, the reviewer who took a case into handling.
     * #389 switched that to the new explicit reviewer_user_id, which is null for
     * every existing case. Copying the handler over reproduces the prior working
     * stock so cases keep their reviewer instead of all reappearing as unassigned.
     */
    public function up(): void
    {
        DB::table('zaken')
            ->whereNull('reviewer_user_id')
            ->whereNotNull('handled_status_set_by_user_id')
            ->whereIn('handled_status_set_by_user_id', fn (Builder $query) => $query->select('id')->from('users'))
            ->update(['reviewer_user_id' => DB::raw('handled_status_set_by_user_id')]);
    }

    /**
     * The backfill copies existing data and cannot be reliably reversed.
     * Reviewer assignments are managed through the application afterwards.
     */
    public function down(): void
    {
        // No-op.
    }
};
