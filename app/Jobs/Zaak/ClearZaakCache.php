<?php

namespace App\Jobs\Zaak;

use App\Models\Zaak;
use App\ValueObjects\OpenNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ClearZaakCache implements ShouldQueue
{
    use Queueable;

    private OpenNotification $notification;

    /**
     * Create a new job instance.
     */
    public function __construct(OpenNotification $notification)
    {
        $this->notification = $notification;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($zaak = Zaak::where('zgw_zaak_url', $this->notification->hoofdObject)->first()) {
            $zaak->clearZgwCache();
        }
    }
}
