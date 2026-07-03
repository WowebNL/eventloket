<?php

declare(strict_types=1);

namespace App\Jobs\Zaak;

use App\EventForm\State\FormState;
use App\EventForm\Submit\ZaakeigenschappenMap;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Zaak;
use App\Services\Zgw\InitiatorRolBuilder;
use App\Services\Zgw\ZaakReadModel;
use App\Services\Zgw\ZaaktypeBlueprint;
use App\Services\Zgw\ZgwResource;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Woweb\Zgw\Connection\ZgwConnection;
use Woweb\Zgw\Facades\Zgw;

/**
 * Zet de initiator-rol op de ZGW-zaak op basis van het initiator-blok
 * in de FormState-snapshot. Twee varianten — matcht OF:
 *
 * - Heeft de aanvrager een KvK-nummer? → `niet_natuurlijk_persoon`
 *   (statutaireNaam, kvkNummer, contactpersoon)
 * - Anders → `natuurlijk_persoon` (voornamen, geslachtsnaam, adres)
 *
 * In de oude flow bestond er al een initiator-rol (door OF aangemaakt)
 * en deed deze job een PUT. In de nieuwe flow maken wij de zaak zelf
 * aan zonder initiator, dus moet hier een POST (nieuw rol) gebeuren.
 * Het initiator-roltype wordt opgezocht in de catalogi.
 */
class UpdateInitiatorZGW implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Zaak $zaak) {}

    public function handle(ZaakeigenschappenMap $map): void
    {
        if (! $this->zaak->zgw_zaak_url) {
            return;
        }

        $state = FormState::fromSnapshot($this->zaak->form_state_snapshot ?? []);
        $initiator = $map->buildInitiator($state);
        if (empty($initiator)) {
            return;
        }

        $connectionName = $this->zaak->zgwConnectionName();
        $connection = Zgw::connection($connectionName);

        $ozZaak = ZaakReadModel::fromArray(ZgwResource::byUrl($connectionName, $this->zaak->zgw_zaak_url.'?expand=rollen'));
        $roltype = $this->findInitiatorRoltype($connection, $ozZaak->zaaktype);
        if (! $roltype) {
            return;
        }

        $rolData = InitiatorRolBuilder::build($ozZaak->url, $roltype, $state, $initiator);
        if ($rolData === null) {
            return;
        }

        $connection->zaken()->rollen()->store($rolData);
    }

    private function findInitiatorRoltype(ZgwConnection $connection, string $zaaktypeUrl): ?string
    {
        $roltypen = $connection->catalogi()->roltypen()->index(['zaaktype' => $zaaktypeUrl]);
        $mapping = MunicipalityZaaktypeMapping::forZaaktype($this->zaak->zaaktype);
        $initiator = ZaaktypeBlueprint::initiatorRoltype($mapping, $roltypen);

        return $initiator['url'] ?? null;
    }
}
