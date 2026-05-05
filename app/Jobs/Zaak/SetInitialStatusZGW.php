<?php

declare(strict_types=1);

namespace App\Jobs\Zaak;

use App\Models\Zaak;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Woweb\Openzaak\Openzaak;

/**
 * Zet de initiële status (statustype met volgnummer 1) op de ZGW-zaak
 * direct nadat de zaak is aangemaakt. Zonder status kent OpenZaak de
 * zaak als 'statusloos'; de ontvangstbevestiging-trigger in
 * ZaakStatusNotificationReceived verwacht dat volgnummer 1 de eerste
 * inkomende status is en slaat de e-mailnotificatie dan terecht over.
 */
class SetInitialStatusZGW implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Zaak $zaak) {}

    public function handle(Openzaak $openzaak): void
    {
        if (! $this->zaak->zgw_zaak_url) {
            return;
        }

        $zaakUrl = $this->zaak->zgw_zaak_url;
        $zaaktype = $this->zaak->zaaktype->zgw_zaaktype_url;

        $statustypen = $openzaak->catalogi()->statustypen()->getAll(['zaaktype' => $zaaktype]);
        $initieel = $statustypen->sortBy('volgnummer')->first();

        if (! $initieel) {
            Log::warning('SetInitialStatusZGW: geen statustype gevonden', [
                'zaak_id' => $this->zaak->id,
                'zaaktype' => $zaaktype,
            ]);

            return;
        }

        $openzaak->zaken()->statussen()->store([
            'zaak' => $zaakUrl,
            'statustype' => $initieel['url'],
            'datumStatusGezet' => now()->toIso8601String(),
        ]);
    }
}
