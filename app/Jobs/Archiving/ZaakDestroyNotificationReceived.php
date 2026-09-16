<?php

namespace App\Jobs\Archiving;

use App\Enums\ZaakDestructionSource;
use App\Models\Zaak;
use App\Services\Archiving\EventloketDataDestroyer;
use App\ValueObjects\OpenNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Acts on a ZGW "destroy" notification for a zaak: the zaaksysteem has removed
 * the zaakdata, so everything Eventloket still holds about that zaak goes too.
 *
 * The same route serves both worlds. On our own OpenZaak the archive module
 * deletes the zaak and this notification comes back to us; on a municipality's
 * own instance the municipality deletes it there and the notification is the
 * only thing that reaches us. Either way Eventloket cleans up its own side and
 * never deletes on somebody else's instance.
 *
 * A notification for a zaak we do not know is normal: a shared ZGW instance
 * carries other organisations' zaken, and we only ever hold our own.
 */
class ZaakDestroyNotificationReceived implements ShouldQueue
{
    use Queueable;

    public function __construct(private OpenNotification $notification) {}

    public function handle(EventloketDataDestroyer $destroyer): void
    {
        // withTrashed: a soft-deleted zaak still holds all of its threads,
        // messages and documents, so it needs the same cleanup.
        $zaak = Zaak::withTrashed()
            ->where('zgw_zaak_url', $this->notification->hoofdObject)
            ->first();

        if ($zaak === null) {
            return;
        }

        $log = $destroyer->destroy($zaak, ZaakDestructionSource::Notification);

        if ($log !== null) {
            Log::info("Destroyed the Eventloket data of zaak [{$log->zaaknummer}] on connection [{$log->zgw_connection}] after a ZGW destroy notification.");
        }
    }
}
