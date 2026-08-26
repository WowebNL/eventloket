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
 * Sets the initiator rol on the ZGW zaak from the initiator block in the
 * FormState snapshot. The variant matches the aanvrager and the connection
 * ({@see InitiatorRolBuilder}):
 *
 * - has a KvK number, own default connection → `niet_natuurlijk_persoon`
 *   (statutaireNaam, annIdentificatie, kvkNummer, contactpersoon)
 * - has a KvK number, any other connection → `vestiging` (kvkNummer,
 *   handelsnaam, contactpersoon, verblijfsadres)
 * - otherwise → `natuurlijk_persoon` (voornamen, geslachtsnaam,
 *   anpIdentificatie, verblijfsadres)
 *
 * In the legacy flow an initiator rol already existed (created by Open Forms)
 * and this job did a PUT. In the native flow we create the zaak ourselves
 * without an initiator, so a POST (new rol) happens here. The initiator
 * roltype is looked up in the catalogi.
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

        $rolData = InitiatorRolBuilder::build(
            $connectionName,
            $ozZaak->url,
            $roltype,
            $state,
            $initiator,
            InitiatorRolBuilder::anpIdentificatieForUser($this->zaak->organiser_user_id),
        );
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
