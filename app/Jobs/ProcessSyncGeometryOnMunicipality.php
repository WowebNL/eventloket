<?php

namespace App\Jobs;

use App\Actions\Geospatial\SyncGeometry;
use App\Models\Municipality;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\WithoutRelations;

class ProcessSyncGeometryOnMunicipality implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        #[WithoutRelations]
        public Municipality $municipality
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        (new SyncGeometry(model: $this->municipality))->execute();
    }
}
