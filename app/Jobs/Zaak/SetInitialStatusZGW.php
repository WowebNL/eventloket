<?php

declare(strict_types=1);

namespace App\Jobs\Zaak;

use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Zaak;
use App\Services\Zgw\ZaaktypeBlueprint;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Woweb\Zgw\Facades\Zgw;

/**
 * Zet de initiële status (statustype met volgnummer 1) op de ZGW-zaak
 * direct nadat de zaak is aangemaakt. Zonder status kent OpenZaak de
 * zaak als 'statusloos'; de ontvangstbevestiging-trigger in
 * ZaakStatusNotificationReceived verwacht dat volgnummer 1 de eerste
 * inkomende status is en slaat de e-mailnotificatie dan terecht over.
 */
class SetInitialStatusZGW implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Zaak $zaak) {}

    public function handle(): void
    {
        if (! $this->zaak->zgw_zaak_url) {
            return;
        }

        $zaakUrl = $this->zaak->zgw_zaak_url;
        $zaaktype = $this->zaak->zgwZaaktypeVersionUrl();

        $connection = Zgw::connection($this->zaak->zgwConnectionName());

        $statustypen = $connection->catalogi()->statustypen()->index(['zaaktype' => $zaaktype])->collect();
        $mapping = MunicipalityZaaktypeMapping::forZaaktype($this->zaak->zaaktype);
        $initieel = ZaaktypeBlueprint::initialStatustype($mapping, $statustypen);

        if (! $initieel) {
            Log::warning('SetInitialStatusZGW: geen statustype gevonden', [
                'zaak_id' => $this->zaak->id,
                'zaaktype' => $zaaktype,
            ]);

            return;
        }

        $connection->zaken()->statussen()->store([
            'zaak' => $zaakUrl,
            'statustype' => $initieel['url'],
            'datumStatusGezet' => now()->toIso8601String(),
        ]);
    }
}
