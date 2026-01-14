<?php

namespace App\Jobs\Zaak;

use App\Models\Zaak;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
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
            $zaak_reference = array_merge([
                'status_name' => $zaak->openzaak->status_name,
                'statustype_url' => $zaak->openzaak->statustype_url,
                'resultaat' => $zaak->openzaak->resultaattype ? $zaak->openzaak->resultaattype['omschrijving'] : null,
            ], $zaak->openzaak->eigenschappen_key_value);

            /** @disregard */
            $zaak->reference_data = new ZaakReferenceData(...array_merge($zaak->reference_data->toArray(), $zaak_reference)); // @phpstan-ignore assign.propertyReadOnly

            $zaak->save();
        }
    }
}
