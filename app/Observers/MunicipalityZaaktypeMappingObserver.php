<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\MunicipalityZaaktypeMapping;
use App\Services\Zgw\ZaaktypeRefresher;

/**
 * When a municipality that runs its own ZGW instance saves a zaaktype-koppeling,
 * refresh the matching local Zaaktype row right away instead of waiting for the
 * next sync. Going through the refresher means a corrected koppeling immediately
 * restores a fallback (or raises a fresh warning when still broken).
 * Municipalities on the shared main connection are linked by name in
 * SyncZaaktypen, so nothing is created here for them.
 */
class MunicipalityZaaktypeMappingObserver
{
    public function __construct(private readonly ZaaktypeRefresher $refresher) {}

    public function saved(MunicipalityZaaktypeMapping $mapping): void
    {
        if (! $mapping->zaaktype_identificatie) {
            return;
        }

        $municipality = $mapping->municipality;

        if ($municipality === null || $municipality->zgwConnection === null) {
            return;
        }

        $this->refresher->refreshOwnInstance($municipality, $mapping->zaaktype_identificatie);
    }
}
