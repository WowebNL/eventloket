<?php

declare(strict_types=1);

namespace App\EventForm\Submit\Steps;

use App\EventForm\State\FormState;
use App\EventForm\Submit\DetermineAanvraagType;
use App\Models\MunicipalityZaaktypeMapping;
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
    public function __construct(private readonly DetermineAanvraagType $determineAanvraagType) {}

    public function execute(FormState $state, Zaaktype $zaaktype): ZaakReadModel
    {
        $connectionName = $zaaktype->zgwConnectionName();

        $bronorganisatie = ZgwConnectionConfig::bronorganisatie($connectionName);

        $payload = [
            'zaaktype' => $this->resolveVersionUrl($connectionName, $zaaktype, $state),
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
     * Resolve the zaaktype version that is valid on the creation date, in the
     * catalogus of the connection the zaak is created in.
     *
     * For a municipality with its own ZGW connection the zaak must reference a
     * zaaktype from that connection's catalogus, not the central OpenZaak one the
     * local row points at. The blueprint mapping (role -> connection-catalogus
     * identificatie) is therefore the preferred source; the local Zaaktype's own
     * identificatie is the fallback for the central connection. Falls back to the
     * stored version url when neither resolves.
     */
    private function resolveVersionUrl(string $connectionName, Zaaktype $zaaktype, FormState $state): string
    {
        $identificatie = $this->mappingIdentificatie($zaaktype, $state) ?? $zaaktype->identificatie;

        if (is_string($identificatie) && $identificatie !== '') {
            try {
                $version = Zgw::connection($connectionName)->catalogi()->zaaktypen()->index([
                    'identificatie' => $identificatie,
                    'datumGeldigheid' => Carbon::now('Europe/Amsterdam')->toDateString(),
                    'status' => 'definitief',
                ])->first();

                if (isset($version['url']) && is_string($version['url']) && $version['url'] !== '') {
                    return $version['url'];
                }
            } catch (Throwable) {
                // fall through to the stored url
            }
        }

        return (string) $zaaktype->zgw_zaaktype_url;
    }

    /**
     * The connection-catalogus zaaktype identificatie from the blueprint mapping
     * for this municipality and aanvraag-type, or null when no mapping applies.
     */
    private function mappingIdentificatie(Zaaktype $zaaktype, FormState $state): ?string
    {
        $municipality = $zaaktype->municipality;

        if ($municipality === null) {
            return null;
        }

        $mapping = MunicipalityZaaktypeMapping::forMunicipalityRole(
            $municipality,
            $this->determineAanvraagType->forState($state),
        );

        $identificatie = $mapping?->zaaktype_identificatie;

        return is_string($identificatie) && $identificatie !== '' ? $identificatie : null;
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
