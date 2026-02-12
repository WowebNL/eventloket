<?php

namespace App\Jobs;

use App\Jobs\Zaak\ClearZaakCache;
use App\Models\Zaak;
use App\Notifications\ZaakStatusChanged;
use App\ValueObjects\OpenNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ZaakStatusNotificationReceived implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(private OpenNotification $notification)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($zaak = Zaak::where('zgw_zaak_url', $this->notification->hoofdObject)->first()) {
            $oldStatus = $zaak->reference_data->status_name;

            dispatch_sync(new ClearZaakCache($this->notification));

            $zaak->refresh();

            if ($oldStatus === $zaak->reference_data->status_name) {
                return;
            }

            foreach ($zaak->organisation->users as $user) {
                /** @var \App\Models\Users\OrganiserUser $user */
                $user->notify(new ZaakStatusChanged($zaak, $oldStatus));
            }
        }
    }
}
