<?php

declare(strict_types=1);

namespace App\Jobs\Zaak;

use App\Models\Zaak;
use App\Services\Zgw\ZaakReadModel;
use App\Services\Zgw\ZgwResource;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Woweb\Zgw\Data\Generated\Catalogi\ZaakTypeData;
use Woweb\Zgw\Facades\Zgw;

/**
 * Vult `einddatumGepland` + `uiterlijkeEinddatumAfdoening` op de ZGW-zaak
 * op basis van de `servicenorm` en `doorlooptijd` van het zaaktype.
 * Geen FormState nodig — puur een berekening op bestaande ZGW-data.
 */
class AddEinddatumZGW implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Zaak $zaak) {}

    public function handle(): void
    {
        if (! $this->zaak->zgw_zaak_url) {
            return;
        }

        $connectionName = $this->zaak->zgwConnectionName();
        $ozZaak = ZaakReadModel::fromArray(ZgwResource::byUrl($connectionName, $this->zaak->zgw_zaak_url));

        if ($ozZaak->uiterlijkeEinddatumAfdoening || $ozZaak->einddatumGepland) {
            return;
        }

        $zaaktype = ZaakTypeData::from(ZgwResource::byUrl($connectionName, $ozZaak->zaaktype));
        // doorlooptijd/servicenorm are CarbonInterval (or null when the catalogus
        // omits them); patch only the dates we can derive.
        $patch = [];
        if ($zaaktype->servicenorm) {
            $patch['einddatumGepland'] = Carbon::parse($ozZaak->startdatum)->add($zaaktype->servicenorm)->format('Y-m-d');
        }
        if ($zaaktype->doorlooptijd) {
            $patch['uiterlijkeEinddatumAfdoening'] = Carbon::parse($ozZaak->startdatum)->add($zaaktype->doorlooptijd)->format('Y-m-d');
        }

        if ($patch === []) {
            return;
        }

        Zgw::connection($connectionName)->zaken()->zaken()->patch($ozZaak->uuid, $patch);
    }
}
