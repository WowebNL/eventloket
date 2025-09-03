<?php

namespace App\Jobs;

use App\Models\AdminInvite;
use App\Models\AdvisoryInvite;
use App\Models\MunicipalityInvite;
use App\Models\OrganisationInvite;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CleanupExpiredInvites implements ShouldQueue
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
        $models = [
            AdminInvite::class,
            AdvisoryInvite::class,
            MunicipalityInvite::class,
            OrganisationInvite::class,
        ];

        $totalDeleted = 0;

        foreach ($models as $model) {
            $deleted = $model::expired()->count();
            $model::expired()->delete();
            $totalDeleted += $deleted;

            Log::info("Deleted {$deleted} expired ".class_basename($model).' records');
        }

        Log::info("Total expired invites deleted: {$totalDeleted}");
    }
}
