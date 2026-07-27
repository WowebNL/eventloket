<?php

declare(strict_types=1);

namespace App\Jobs\Zaak;

use App\Models\Zaak;
use App\Services\Zgw\ZgwConnectionResolver;
use App\ValueObjects\OpenNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Woweb\Zgw\Api\Endpoints\DirectEndpoint;
use Woweb\Zgw\Facades\Zgw;

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

        try {
            $connectionName = app(ZgwConnectionResolver::class)->forUrl($besluitUrl);
            $besluit = (new DirectEndpoint(Zgw::connection($connectionName)))->getByUrl($besluitUrl);
        } catch (\Throwable $e) {
            Log::warning('Could not read besluit for a besluiten notification: '.$e->getMessage(), [
                'besluit' => $besluitUrl,
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
