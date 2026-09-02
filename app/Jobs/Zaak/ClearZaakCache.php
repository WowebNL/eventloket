<?php

namespace App\Jobs\Zaak;

use App\Actions\UpdateZaakReferenceData;
use App\Jobs\ProcessOpenNotification;
use App\Models\Zaak;
use App\ValueObjects\OpenNotification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Refreshes the reference data of the zaak a zaakeigenschap notification
 * concerns, by dropping its cached ZGW read and building it again.
 *
 * One zaak receives a burst of these notifications: eigenschappen are written
 * to the zaaksysteem one at a time, so a single submission fires one per
 * eigenschap, all carrying the same hoofdObject. Every one of them would
 * otherwise mean its own full ZGW refetch plus save. The dispatch delay in
 * {@see ProcessOpenNotification} combined with ShouldBeUnique collapses that
 * burst into one refresh that reads all the values at once; the refresh is
 * idempotent, so a straggler arriving after the unique window just reads the
 * same state again.
 */
class ClearZaakCache implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Safely exceeds the dispatch delay plus the refresh itself. */
    public int $uniqueFor = 600;

    private OpenNotification $notification;

    /**
     * Create a new job instance.
     */
    public function __construct(OpenNotification $notification)
    {
        $this->notification = $notification;
    }

    /**
     * Unique per zaak, not per notification: the whole point is that the
     * eigenschappen of one zaak are refreshed together.
     */
    public function uniqueId(): string
    {
        return 'clear-zaak-cache:'.md5($this->notification->hoofdObject);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($zaak = Zaak::where('zgw_zaak_url', $this->notification->hoofdObject)->first()) {
            UpdateZaakReferenceData::handle($zaak);
        }
    }
}
