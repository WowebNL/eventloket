<?php

namespace App\Actions;

use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Zaak;
use App\Services\Zgw\ZaaktypeBlueprint;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;

class UpdateZaakReferenceData
{
    public static function handle(Zaak $zaak)
    {
        $zaak->clearZgwCache();
        $current = $zaak->reference_data->toArray();
        $zaak_reference = array_merge([
            'status_name' => $zaak->openzaak->status_name,
            'statustype_url' => $zaak->openzaak->statustype_url,
            'resultaat' => $zaak->openzaak->resultaattype ? $zaak->openzaak->resultaattype['omschrijving'] : null,
            'resultaattype_url' => $zaak->openzaak->resultaat ? $zaak->openzaak->resultaat['resultaattype'] : null,
        ], ZaakReferenceData::normalizeEigenschapDates(
            ZaaktypeBlueprint::logicalEigenschappen(
                // The eigenschappen come back keyed by their ZGW naam. Where the
                // koppeling renames them, translate back to the logical keys the
                // reference data is built from, otherwise a value changed in the
                // zaaksysteem lands under an unknown key and is dropped.
                MunicipalityZaaktypeMapping::forZaaktype($zaak->zaaktype),
                $zaak->openzaak->eigenschappen_key_value,
            ),
            // Dates come back in the ZGW wire form; the reference data keeps
            // ISO 8601, which is what the event queries compare against.
            $current,
        ));

        /** @disregard */
        $zaak->reference_data = new ZaakReferenceData(...array_merge($current, $zaak_reference)); // @phpstan-ignore assign.propertyReadOnly

        $zaak->save();
    }
}
