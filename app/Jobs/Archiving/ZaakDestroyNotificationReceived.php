<?php

namespace App\Jobs\Archiving;

use App\Models\Zaak;
use App\Services\Archiving\EventloketDataDestroyer;
use App\Services\Zgw\ZgwResource;
use App\ValueObjects\OpenNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;
use Woweb\Zgw\Exceptions\ApiRequestException;

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
 * Two checks stand in front of the destruction, because a notification is a
 * claim from outside and what follows cannot be undone:
 *
 * 1. The caller must own the zaak. The sender connection is taken from the
 *    validated token rather than from the payload, so a notification only ever
 *    applies to zaken of the connection that sent it.
 * 2. The zaak must really be gone. The claim is checked against the source
 *    before anything is removed, so what is destroyed here follows the
 *    zaaksysteem rather than the notification.
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

        if (! $this->senderOwnsZaak($zaak)) {
            return;
        }

        if (! $this->zaakIsReallyGone($zaak)) {
            return;
        }

        $log = $destroyer->destroy($zaak);

        if ($log !== null) {
            Log::info("Destroyed the Eventloket data of zaak [{$log->zaaknummer}] on connection [{$log->zgw_connection}] after a ZGW destroy notification.");
        }
    }

    /**
     * Whether the credential that delivered this notification belongs to the
     * connection the zaak lives on.
     *
     * The host allowlist on the request establishes that the url belongs to some
     * configured connection, not that it belongs to this caller. That is what
     * this adds.
     */
    private function senderOwnsZaak(Zaak $zaak): bool
    {
        $sender = $this->notification->senderConnection;
        $owner = $zaak->zgwConnectionName();

        if ($sender !== null && $sender === $owner) {
            return true;
        }

        Log::warning('Refused a ZGW destroy notification from a connection that does not own the zaak.', [
            'zaak_id' => $zaak->id,
            'zaaknummer' => $zaak->public_id,
            'zaak_connection' => $owner,
            'sender_connection' => $sender,
            'hoofd_object' => $this->notification->hoofdObject,
        ]);

        return false;
    }

    /**
     * Whether the zaaksysteem confirms the zaak is gone.
     *
     * A 404 is the only answer that justifies destroying anything. A readable
     * zaak means the notification does not match reality and is dropped. A read
     * that fails for any other reason decides nothing, so the job is retried
     * rather than treated as confirmation: of the three outcomes only one is
     * safe to act on.
     *
     * ZgwResource::byUrl() enforces the connection's own host allowlist, so the
     * url cannot send the connection's credential somewhere else.
     */
    private function zaakIsReallyGone(Zaak $zaak): bool
    {
        try {
            ZgwResource::byUrl($zaak->zgwConnectionName(), $this->notification->hoofdObject);
        } catch (ApiRequestException $exception) {
            if ($exception->getResponse()->status() === 404) {
                return true;
            }

            throw $exception;
        } catch (Throwable $exception) {
            // Not a decision either way, so it has to be retried rather than
            // swallowed: never destroy on an inconclusive read.
            throw $exception;
        }

        Log::warning('Ignored a ZGW destroy notification for a zaak that still exists at the source.', [
            'zaak_id' => $zaak->id,
            'zaaknummer' => $zaak->public_id,
            'hoofd_object' => $this->notification->hoofdObject,
        ]);

        return false;
    }
}
