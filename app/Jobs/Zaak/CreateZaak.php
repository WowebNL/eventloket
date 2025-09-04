<?php

namespace App\Jobs\Zaak;

use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use App\ValueObjects\OzZaak;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Woweb\Openzaak\Openzaak;

class CreateZaak implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(private string $zaakUrlZGW) {}

    /**
     * Execute the job.
     */
    public function handle(Openzaak $openzaak): void
    {
        $ozZaak = new OzZaak(...$openzaak->get($this->zaakUrlZGW.'?expand=zaakobjecten,eigenschappen,status,status.statustype')->toArray());
        $zaaktype = Zaaktype::where(['zgw_zaaktype_url' => $ozZaak->zaaktype, 'is_active' => true])->first();

        if (! $zaaktype) {
            // zaaktype not found in local database or not active
            Log::warning('Zaaktype not found or inactive', ['zaaktype' => $ozZaak->zaaktype]);

            return;
        }
        $zaak = Zaak::updateOrCreate(
            ['zgw_zaak_url' => $ozZaak->url],
            [
                'public_id' => $ozZaak->identificatie,
                'zaaktype_id' => $zaaktype->id,
                'data_object_url' => $ozZaak->data_object_url,
                'reference_data' => new ZaakReferenceData(
                    ...array_merge(
                        $ozZaak->eigenschappen_key_value,
                        [
                            'registratiedatum' => $ozZaak->registratiedatum,
                            'status_name' => $ozZaak->status_name,
                        ]
                    )
                ),
            ]
        );
    }
}
