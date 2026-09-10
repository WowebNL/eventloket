<?php

declare(strict_types=1);

namespace App\EventForm\Submit\Steps;

use App\EventForm\State\FormState;
use App\EventForm\Submit\DetermineAanvraagType;
use App\EventForm\Submit\ResolveZaaktype;
use App\Exceptions\ZaaktypeConnectionMismatchException;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Zaaktype;
use App\Services\Zgw\ZaakReadModel;
use App\Services\Zgw\ZgwConnectionConfig;
use App\Services\Zgw\ZgwConnectionResolver;
use App\Services\Zgw\ZgwResource;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
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
    public function __construct(
        private readonly DetermineAanvraagType $determineAanvraagType,
        private readonly ZgwConnectionResolver $connections,
    ) {}

    public function execute(FormState $state, Zaaktype $zaaktype): ZaakReadModel
    {
        $connectionName = $zaaktype->zgwConnectionName();

        $bronorganisatie = ZgwConnectionConfig::bronorganisatie($connectionName);

        $zaaktypeUrl = $this->resolveVersionUrl($connectionName, $zaaktype, $state);

        $this->assertZaaktypeBelongsToConnection($connectionName, $zaaktypeUrl, $zaaktype);

        $payload = [
            'zaaktype' => $zaaktypeUrl,
            'bronorganisatie' => $bronorganisatie,
            'verantwoordelijkeOrganisatie' => $bronorganisatie,
            'startdatum' => Carbon::now('Europe/Amsterdam')->toDateString(),
            'registratiedatum' => Carbon::now('Europe/Amsterdam')->toDateString(),
            'omschrijving' => $this->omschrijving($state),
            'toelichting' => $this->toelichting($state),
        ];

        $data = Zgw::connection($connectionName)->zaken()->zaken()->store($payload);

        $this->logCreationOnFallbackConnection($zaaktype, $connectionName, $data);

        return ZaakReadModel::fromArray(ZgwResource::ensureUuid($data));
    }

    /**
     * Last guard before the payload leaves: the zaaktype url must be hosted by
     * the connection the zaak is created on.
     *
     * It sits deliberately *after* the fallback logic in
     * {@see ResolveZaaktype}, which keeps zaaktype and
     * connection together whenever it can, so this only fires where that could
     * not resolve it. A mismatch here is therefore a defect and not a state to
     * paper over: without this the payload is sent to an instance that cannot
     * resolve the zaaktype, and the submit fails on a validation error from the
     * receiving side that names neither of the two configurations involved.
     *
     * @throws ZaaktypeConnectionMismatchException
     */
    private function assertZaaktypeBelongsToConnection(string $connectionName, string $zaaktypeUrl, Zaaktype $zaaktype): void
    {
        if ($this->connections->connectionServesUrl($connectionName, $zaaktypeUrl)) {
            return;
        }

        $exception = new ZaaktypeConnectionMismatchException(
            zaaktypeUrl: $zaaktypeUrl,
            connectionName: $connectionName,
            zaaktypeId: $zaaktype->id,
            municipalityId: $zaaktype->municipality_id,
        );

        Log::error('Zaaktype url does not belong to the ZGW connection the zaak is created on; not creating the zaak.', [
            'municipality_id' => $zaaktype->municipality_id,
            'connection' => $connectionName,
            'zaaktype_connection' => $zaaktype->getAttribute('connection'),
            'zaaktype_id' => $zaaktype->id,
            'zaaktype_url' => $zaaktypeUrl,
        ]);

        throw $exception;
    }

    /**
     * Record a zaak that was created on the main connection while its
     * municipality runs its own one.
     *
     * The fallback keeps submissions working, but it puts the zaak somewhere the
     * municipality does not expect it, so it has to stay findable afterwards:
     * which municipality, which connection was meant, why it fell back, and which
     * zaak it produced. Without the zaak identification this is a signal that
     * something happened; with it, it is a list of the zaken to move.
     *
     * @param  array<string, mixed>  $data
     */
    private function logCreationOnFallbackConnection(Zaaktype $zaaktype, string $connectionName, array $data): void
    {
        if ($connectionName !== ZgwConnectionResolver::DEFAULT_CONNECTION) {
            return;
        }

        $municipality = $zaaktype->municipality;

        if ($municipality === null || $municipality->zgwConnection === null) {
            return;
        }

        Log::warning('Zaak created on the main ZGW connection while the municipality has its own connection configured.', [
            'municipality_id' => $municipality->id,
            'municipality' => $municipality->name,
            'connection' => $connectionName,
            'intended_connection' => "gemeente_{$municipality->id}",
            'reason' => $this->connections->mainFallbackReason($municipality),
            'zaak_identificatie' => is_string($data['identificatie'] ?? null) ? $data['identificatie'] : null,
            'zaak_url' => is_string($data['url'] ?? null) ? $data['url'] : null,
            'zaaktype_id' => $zaaktype->id,
        ]);
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
        $identificatie = $this->mappingIdentificatie($connectionName, $zaaktype, $state) ?? $zaaktype->identificatie;

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
    private function mappingIdentificatie(string $connectionName, Zaaktype $zaaktype, FormState $state): ?string
    {
        $municipality = $zaaktype->municipality;

        if ($municipality === null) {
            return null;
        }

        // During a main-fallback the zaak is created on "main" while the mapping's
        // identificatie belongs to the municipality's own catalogus; skip it so the
        // local row's own (main-catalogus) identificatie resolves the version.
        if ($municipality->zgwConnection !== null && $connectionName === ZgwConnectionResolver::DEFAULT_CONNECTION) {
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
