<?php

declare(strict_types=1);

namespace App\Jobs\Zaak;

use App\Models\Zaak;
use App\Services\Zgw\ZaakReadModel;
use App\Services\Zgw\ZgwResource;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Woweb\Zgw\Facades\Zgw;

/**
 * Registers the event's location names as a zaakobject of type "overige" with
 * objectTypeOverige "GlobaleLocatie". The value is the composed location-names
 * string already present on the zaak's reference data (locaties_evenement).
 *
 * This applies to every connection, including our own OpenZaak (main).
 */
class AddGlobaleLocatieZGW implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Zaak $zaak) {}

    public function handle(): void
    {
        if (! $this->zaak->zgw_zaak_url) {
            return;
        }

        $locaties = $this->zaak->reference_data->locaties_evenement;
        if (! is_string($locaties) || $locaties === '') {
            return;
        }

        $connectionName = $this->zaak->zgwConnectionName();
        $connection = Zgw::connection($connectionName);

        $ozZaak = ZaakReadModel::fromArray(ZgwResource::byUrl($connectionName, $this->zaak->zgw_zaak_url));

        $connection->zaken()->zaakobjecten()->store([
            'zaak' => $ozZaak->url,
            'objectType' => 'overige',
            'objectTypeOverige' => 'GlobaleLocatie',
            'relatieomschrijving' => 'Globale locatie van het evenement',
            'objectIdentificatie' => [
                'naam' => $locaties,
            ],
        ]);
    }
}
