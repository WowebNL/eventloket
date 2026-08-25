<?php

declare(strict_types=1);

namespace App\Jobs\Zaak;

use App\Models\Zaak;
use App\Services\Zgw\NotificationResourceReader;
use App\ValueObjects\OpenNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Clears a zaak's cached besluiten when the besluiten channel reports a change.
 *
 * The notification's hoofdObject is the besluit url, not the zaak url, so the
 * besluit is read to find the zaak it belongs to. Without this a besluit taken
 * in the ZGW backend itself (rather than through Eventloket) never reached the
 * zaak page.
 */
class BesluitNotificationReceived implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly OpenNotification $notification) {}

    public function handle(): void
    {
        $besluitUrl = $this->notification->hoofdObject;
        $reader = app(NotificationResourceReader::class);

        // Deliberately outside the catch below: failing to work out which
        // connection owns the besluit is not "could not read this besluit". It
        // used to end up in that warning, which turned every besluit taken on a
        // shared instance into a silent drop that left the zaak page stale.
        $resource = $reader->resolve($this->notification);

        if ($resource === null) {
            // Another organisation's besluit on a shared instance.
            return;
        }

        try {
            $besluit = $reader->read($resource, $this->notification);
        } catch (\Throwable $e) {
            Log::warning('Could not read besluit for a besluiten notification: '.$e->getMessage(), [
                'besluit' => $besluitUrl,
                'connection' => $resource->connection,
            ]);

            return;
        }

        $zaakUrl = $besluit['zaak'] ?? null;
        if (! is_string($zaakUrl) || $zaakUrl === '') {
            return;
        }

        Zaak::where('zgw_zaak_url', $zaakUrl)->first()?->clearZgwCache();
    }
}
