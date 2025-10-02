<?php

namespace App\Jobs;

use Filament\Actions\Exports\Models\Export;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CleanupExports implements ShouldQueue
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
        foreach (Export::where('created_at', '<', now()->subDays(7))->get() as $export) {
            $export->deleteFileDirectory();
            $export->delete();
        }
    }
}
