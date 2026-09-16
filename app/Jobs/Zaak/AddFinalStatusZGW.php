<?php

namespace App\Jobs\Zaak;

use App\Models\MunicipalityZaaktypeMapping;
use App\Services\Zgw\ZaaktypeBlueprint;
use App\ValueObjects\FinishZaakObject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Woweb\Zgw\Data\Generated\Catalogi\StatusTypeData;
use Woweb\Zgw\Facades\Zgw;

class AddFinalStatusZGW implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public FinishZaakObject $finishZaakObject) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $zaak = $this->finishZaakObject->zaak;
        $connection = Zgw::connection($zaak->zgwConnectionName());

        $statustypen = $connection->catalogi()->statustypen()->index(['zaaktype' => $zaak->openzaak->zaaktype])->collect();
        $mapping = MunicipalityZaaktypeMapping::forZaaktype($zaak->zaaktype);
        $eind = ZaaktypeBlueprint::eindStatustype($mapping, $statustypen);

        if (! $eind) {
            throw new RuntimeException(sprintf('Geen eind-statustype gevonden voor zaak %s.', $zaak->id));
        }

        $finalStatusType = StatusTypeData::from($eind);
        $connection->zaken()->statussen()->store(array_merge($this->finishZaakObject->getPartialStatusData(), ['statustype' => (string) $finalStatusType->url]));
    }
}
