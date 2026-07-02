<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\MunicipalityZaaktypeMapping;
use App\Services\Zgw\MappedZaaktypeSync;

/**
 * When a municipality that runs its own ZGW instance saves a zaaktype-koppeling,
 * ensure the matching local Zaaktype row exists right away instead of waiting for
 * the next scheduled sync. Municipalities on the shared main connection are linked
 * by name in SyncZaaktypen, so nothing is created here for them.
 */
class MunicipalityZaaktypeMappingObserver
{
    public function __construct(private readonly MappedZaaktypeSync $sync) {}

    public function saved(MunicipalityZaaktypeMapping $mapping): void
    {
        if (! $mapping->zaaktype_identificatie) {
            return;
        }

        $municipality = $mapping->municipality;

        if ($municipality === null || $municipality->zgwConnection === null) {
            return;
        }

        $this->sync->ensure($municipality, $mapping->zaaktype_identificatie);
    }
}
