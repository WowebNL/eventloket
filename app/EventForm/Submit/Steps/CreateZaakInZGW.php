<?php

declare(strict_types=1);

namespace App\EventForm\Submit\Steps;

use App\EventForm\State\FormState;
use App\Models\Zaaktype;
use App\Services\Zgw\ZaakReadModel;
use App\Services\Zgw\ZgwConnectionConfig;
use App\Services\Zgw\ZgwResource;
use Carbon\Carbon;
use Throwable;
use Woweb\Zgw\Facades\Zgw;

/**
 * Synchrone eerste ZGW-stap van een submit: maakt een basiszaak aan bij
 * OpenZaak zodat we direct een zaaknummer terug hebben. Alle verrijking
 * (eigenschappen, einddatum, initiator, geometry, doorkomsten) gaat
 * daarna async via queue-jobs.
 *
 * Vervangt wat in de oude OF-flow door Open Forms zelf gedaan werd.
 */
final class CreateZaakInZGW
{
    public function execute(FormState $state, Zaaktype $zaaktype): ZaakReadModel
    {
        $connectionName = $zaaktype->zgwConnectionName();

        $bronorganisatie = ZgwConnectionConfig::bronorganisatie($connectionName);

        $payload = [
            'zaaktype' => $this->resolveVersionUrl($connectionName, $zaaktype),
            'bronorganisatie' => $bronorganisatie,
            'verantwoordelijkeOrganisatie' => $bronorganisatie,
            'startdatum' => Carbon::now('Europe/Amsterdam')->toDateString(),
            'registratiedatum' => Carbon::now('Europe/Amsterdam')->toDateString(),
            'omschrijving' => $this->omschrijving($state),
            'toelichting' => $this->toelichting($state),
        ];

        $data = Zgw::connection($connectionName)->zaken()->zaken()->store($payload);

        return ZaakReadModel::fromArray(ZgwResource::ensureUuid($data));
    }

    /**
     * Resolve the zaaktype version that is valid on the creation date.
     *
     * The local Zaaktype is logical (one row per identificatie); the actual ZGW
     * version is resolved here via the standard ZTC datumGeldigheid/status filters
     * so a zaak is always linked to the version in force today. Falls back to the
     * stored (latest) version url when the lookup yields nothing or fails.
     */
    private function resolveVersionUrl(string $connectionName, Zaaktype $zaaktype): string
    {
        if (! $zaaktype->identificatie) {
            return (string) $zaaktype->zgw_zaaktype_url;
        }

        try {
            $version = Zgw::connection($connectionName)->catalogi()->zaaktypen()->index([
                'identificatie' => $zaaktype->identificatie,
                'datumGeldigheid' => Carbon::now('Europe/Amsterdam')->toDateString(),
                'status' => 'definitief',
            ])->first();

            return $version['url'] ?? (string) $zaaktype->zgw_zaaktype_url;
        } catch (Throwable) {
            return (string) $zaaktype->zgw_zaaktype_url;
        }
    }

    private function omschrijving(FormState $state): string
    {
        $naam = $state->get('watIsDeNaamVanHetEvenementVergunning');

        return is_string($naam) && $naam !== '' ? mb_substr($naam, 0, 80) : 'Evenement-aanvraag';
    }

    private function toelichting(FormState $state): string
    {
        $omschrijving = $state->get('geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning');

        return is_string($omschrijving) ? mb_substr($omschrijving, 0, 1000) : '';
    }
}
