<?php

namespace App\Actions;

use App\Models\Zaak;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;

class UpdateZaakReferenceData
{
    public static function handle(Zaak $zaak)
    {
        $zaak->clearZgwCache();
        $zaak_reference = array_merge([
            'status_name' => $zaak->openzaak->status_name,
            'statustype_url' => $zaak->openzaak->statustype_url,
            'resultaat' => $zaak->openzaak->resultaattype ? $zaak->openzaak->resultaattype['omschrijving'] : null,
            'resultaattype_url' => $zaak->openzaak->resultaat ? $zaak->openzaak->resultaat['resultaattype'] : null,
        ], $zaak->openzaak->eigenschappen_key_value);

        /** @disregard */
        $zaak->reference_data = new ZaakReferenceData(...array_merge($zaak->reference_data->toArray(), $zaak_reference)); // @phpstan-ignore assign.propertyReadOnly

        $zaak->save();
    }
}
