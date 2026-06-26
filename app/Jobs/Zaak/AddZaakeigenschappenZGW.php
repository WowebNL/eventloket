<?php

declare(strict_types=1);

namespace App\Jobs\Zaak;

use App\EventForm\State\FormState;
use App\EventForm\Submit\ZaakeigenschappenMap;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Zaak;
use App\Normalizers\OpenFormsNormalizer;
use App\Services\Zgw\ZaaktypeBlueprint;
use App\Services\Zgw\ZgwConnectionConfig;
use App\Services\Zgw\ZgwResource;
use App\Services\Zgw\ZaakReadModel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Woweb\Zgw\Data\Generated\Catalogi\EigenschapData;
use Woweb\Zgw\Facades\Zgw;

/**
 * Schrijft zaakeigenschappen op de ZGW-zaak op basis van de FormState
 * in `$zaak->form_state_snapshot`.
 *
 * Vervangt de oude implementatie die uit Objects API las. De
 * mapping-lijst staat in `ZaakeigenschappenMap`, OF's oude gedrag
 * wordt 1-op-1 gevolgd: eigenschap niet in catalogus → stil overslaan;
 * lege waarde → stil overslaan; al aanwezig op de zaak → overslaan.
 */
class AddZaakeigenschappenZGW implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Zaak $zaak) {}

    public function handle(ZaakeigenschappenMap $map): void
    {
        if (! $this->zaak->zgw_zaak_url) {
            return;
        }

        $connectionName = $this->zaak->zgwConnectionName();
        $connection = Zgw::connection($connectionName);

        $state = FormState::fromSnapshot($this->zaak->form_state_snapshot ?? []);
        $ozZaak = ZaakReadModel::fromArray(ZgwResource::byUrl($connectionName, $this->zaak->zgw_zaak_url.'?expand=eigenschappen'));
        $catalogiEigenschappen = $connection->catalogi()->eigenschappen()
            ->index(['zaaktype' => $ozZaak->zaaktype])
            ->collect()
            ->map(fn ($eigenschap) => EigenschapData::from($eigenschap));

        $eigenschappen = $map->buildEigenschappen($state);

        // formsubmission_id: in OF een submission-kenmerk, bij ons het
        // lokale public_id (= OpenZaak identificatie).
        if ($this->zaak->public_id) {
            $eigenschappen[] = ['formsubmission_id' => $this->zaak->public_id];
        }

        $mapping = MunicipalityZaaktypeMapping::forZaaktype($this->zaak->zaaktype);

        foreach ($eigenschappen as $eigenschap) {
            // The logical key maps to the concrete eigenschap naam in this
            // catalogus via the blueprint (default: identity).
            $naam = ZaaktypeBlueprint::eigenschapNaam($mapping, (string) key($eigenschap));
            $waarde = current($eigenschap);

            if (Arr::first($ozZaak->eigenschappen, fn ($e) => $e->naam === $naam)) {
                continue;
            }

            $catalogiEigenschap = $catalogiEigenschappen->firstWhere('naam', $naam);
            if (! $catalogiEigenschap) {
                continue;
            }

            if (is_string($waarde) && (str_starts_with($waarde, '[') || str_starts_with($waarde, '{'))) {
                $waarde = OpenFormsNormalizer::normalizeJson($waarde);
            }

            if ($waarde === null || $waarde === '' || $waarde === []) {
                continue;
            }

            $waardeString = is_scalar($waarde) ? (string) $waarde : (string) json_encode($waarde);

            $connection->zaken()->zaken()->zaakeigenschappen(basename($this->zaak->zgw_zaak_url))->store([
                'zaak' => $ozZaak->url,
                'eigenschap' => (string) $catalogiEigenschap->url,
                'waarde' => ZgwConnectionConfig::formatEigenschapWaarde($connectionName, $waardeString),
            ]);
        }
    }
}
