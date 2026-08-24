<?php

declare(strict_types=1);

namespace App\Jobs\Zaak;

use App\Actions\Geospatial\CheckIntersects;
use App\EventForm\State\FormState;
use App\EventForm\Submit\EventLocationGeometryBuilder;
use App\EventForm\Submit\MapFormStateToReferenceData;
use App\EventForm\Submit\ZaakeigenschappenMap;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Services\Zgw\InitiatorRolBuilder;
use App\Services\Zgw\ZaakReadModel;
use App\Services\Zgw\ZaaktypeBlueprint;
use App\Services\Zgw\ZgwConnectionConfig;
use App\Services\Zgw\ZgwResource;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Brick\Geo\Curve;
use Brick\Geo\Engine\PdoEngine;
use Brick\Geo\Geometry;
use Brick\Geo\GeometryCollection;
use Brick\Geo\Point;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Woweb\Zgw\Connection\ZgwConnection;
use Woweb\Zgw\Data\Generated\Catalogi\EigenschapData;
use Woweb\Zgw\Facades\Zgw;

/**
 * For route events (a zaaktype with `triggers_route_check = true`): creates
 * a "doorkomst" deelzaak for every municipality a route passes through
 * (excluding the start and end municipality) and copies the relevant
 * eigenschappen / initiator / documents / initial status.
 *
 * Input is a `Zaak`; the routes come from `form_state_snapshot` via
 * `ZaakeigenschappenMap::buildEventLocation()`. An event can have more than
 * one route drawn on the map, so all of them are read and the result is the
 * union of the municipalities they cross, minus the start and end
 * municipalities of all routes together.
 */
class CreateDoorkomstZaken implements ShouldQueue
{
    use Queueable;

    /**
     * Memoized omschrijving of the hoofdzaak's aanvraag informatieobjecttype, used
     * to recognise the aanvraag PDF when copying documents cross-instance. Resolved
     * lazily once per job run ({@see self::hoofdAanvraagOmschrijving()}).
     */
    private ?string $hoofdAanvraagOmschrijving = null;

    private bool $hoofdAanvraagResolved = false;

    public function __construct(public readonly Zaak $zaak) {}

    public function handle(ZaakeigenschappenMap $map): void
    {
        if (! $this->zaak->zgw_zaak_url || ! $this->zaak->zaaktype) {
            return;
        }
        if (! $this->zaak->zaaktype->effectiveTriggersRouteCheck()) {
            return;
        }

        $state = FormState::fromSnapshot($this->zaak->form_state_snapshot ?? []);
        $lines = $this->extractLines($map->buildEventLocation($state));
        if ($lines === []) {
            return;
        }

        $engine = new PdoEngine(DB::connection()->getPdo());
        $checkIntersects = new CheckIntersects($engine);

        $hoofdZaakMuniBrk = $this->zaak->municipality?->brk_identification;

        $passing = $this->passingMunicipalities($lines, $checkIntersects)
            ->reject(fn ($m) => $hoofdZaakMuniBrk && $m->brk_identification === $hoofdZaakMuniBrk);
        if ($passing->isEmpty()) {
            return;
        }

        $hoofdConnectionName = $this->zaak->zgwConnectionName();

        $ozZaak = ZaakReadModel::fromArray(ZgwResource::byUrl(
            $hoofdConnectionName,
            $this->zaak->zgw_zaak_url.'?expand=zaakobjecten,eigenschappen,rollen,zaakinformatieobjecten'
        ));

        // The initiator is rebuilt from the form's aanvrager data (same source as
        // the hoofdzaak), not copied from the hoofdzaak ZGW rol: that rol's
        // betrokkeneIdentificatie is empty and its betrokkene url is not portable
        // across instances.
        $initiator = $map->buildInitiator($state);

        foreach ($passing as $muniRef) {
            $this->createDeelzaakFor($hoofdConnectionName, $ozZaak, $muniRef, $state, $initiator);
        }
    }

    /**
     * All routes drawn on the map, parsed with the very same parser the submit
     * flow uses. That parser understands both the current map state (one
     * FeatureCollection holding N routes) and the older shapes kept in existing
     * drafts, so the job and the submit flow cannot disagree about what "the
     * routes" are.
     *
     * @param  array<string, mixed>  $eventLocation
     * @return list<Geometry>
     */
    private function extractLines(array $eventLocation): array
    {
        $line = $eventLocation['line'] ?? null;
        if (empty($line) || $line === 'None') {
            return [];
        }

        return array_values(array_filter(
            EventLocationGeometryBuilder::parseLines($line),
            static fn (Geometry $geometry) => ! $geometry->isEmpty(),
        ));
    }

    /**
     * The union of the municipalities the routes pass through, minus the start
     * and end municipalities of ALL routes. The exclusion is deliberately global
     * and not per route: a municipality where any route begins or ends is a
     * start or end municipality for this event, so it never gets a doorkomst
     * deelzaak, not even when another route merely passes through it.
     *
     * @param  list<Geometry>  $lines
     * @return Collection<int, Municipality>
     */
    private function passingMunicipalities(array $lines, CheckIntersects $checkIntersects): Collection
    {
        $excluded = [];
        $passing = new Collection;

        foreach ($lines as $line) {
            foreach ($this->boundaryPoints($line) as $point) {
                foreach ($checkIntersects->checkIntersectsWithModels($point) as $municipality) {
                    $excluded[] = $municipality->brk_identification;
                }
            }

            $passing = $passing->merge($checkIntersects->checkIntersectsWithModels($line));
        }

        return $passing
            ->reject(fn ($m) => in_array($m->brk_identification, $excluded, true))
            ->unique('brk_identification')
            ->values();
    }

    /**
     * Start and end points of a route. A route drawn as a MultiLineString has no
     * start point of its own, so its parts are walked instead.
     *
     * @return list<Point>
     */
    private function boundaryPoints(Geometry $geometry): array
    {
        if ($geometry instanceof Curve) {
            return $geometry->isEmpty() ? [] : [$geometry->startPoint(), $geometry->endPoint()];
        }

        if ($geometry instanceof GeometryCollection) {
            $points = [];
            foreach ($geometry->geometries() as $part) {
                foreach ($this->boundaryPoints($part) as $point) {
                    $points[] = $point;
                }
            }

            return $points;
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $initiator  output of ZaakeigenschappenMap::buildInitiator()
     */
    private function createDeelzaakFor(string $hoofdConnectionName, ZaakReadModel $hoofdZaak, Municipality $muniRef, FormState $state, array $initiator): void
    {
        /** @var Municipality|null $municipality */
        $municipality = Municipality::where('brk_identification', $muniRef->brk_identification)->first();

        if (! $municipality) {
            return;
        }

        // Resolve via the role=Doorkomst blueprint (own-instance municipalities)
        // with a fallback to the legacy doorkomst_zaaktype_id. A municipality
        // without any doorkomst zaaktype configured gets no deelzaak.
        $doorkomstZaaktype = $municipality->resolveDoorkomstZaaktype();
        if (! $doorkomstZaaktype) {
            return;
        }

        // Idempotency: never create a second doorkomst zaak for the same
        // (hoofdzaak × zaaktype). Tracked locally because the ZGW hoofdzaak/deelzaak
        // relationship does not exist for cross-instance doorkomst zaken.
        $alreadyExists = Zaak::query()
            ->where('hoofdzaak_id', $this->zaak->id)
            ->where('zaaktype_id', $doorkomstZaaktype->id)
            ->exists();
        if ($alreadyExists) {
            return;
        }

        // The deelzaak is created in the connection that hosts its doorkomst
        // zaaktype: the municipality's own instance when it has its own doorkomst
        // zaaktype, or main when it falls back to a main one. This keeps the
        // deelzaak and its zaaktype in the same instance. Reads from the hoofdzaak
        // keep using the hoofdzaak connection.
        $deelConnectionName = $doorkomstZaaktype->zgwConnectionName();
        $deelConnection = Zgw::connection($deelConnectionName);

        // The koppeling of the doorkomst zaaktype decides how its catalogus names
        // the eigenschappen, both when writing them and when reading them back.
        $deelMapping = MunicipalityZaaktypeMapping::forZaaktype($doorkomstZaaktype);

        $payload = [
            'zaaktype' => $doorkomstZaaktype->zgw_zaaktype_url,
            'bronorganisatie' => $hoofdZaak->bronorganisatie,
            'verantwoordelijkeOrganisatie' => $hoofdZaak->bronorganisatie,
            'startdatum' => $hoofdZaak->startdatum,
            'omschrijving' => $hoofdZaak->omschrijving,
            'zaakgeometrie' => $hoofdZaak->zaakgeometrie,
        ];

        // ZGW only relates hoofdzaak/deelzaak within one instance: OpenZaak
        // validates the hoofdzaak as one of its own zaken. Only link it when the
        // doorkomst zaaktype lives in the same instance as the hoofdzaak; otherwise
        // create a standalone zaak (the relationship is kept locally via hoofdzaak_id).
        if ($deelConnectionName === $hoofdConnectionName) {
            $payload['hoofdzaak'] = $hoofdZaak->url;
        }

        $response = $deelConnection->zaken()->zaken()->store($payload);

        $newZaakUrl = $response['url'] ?? null;
        if (! $newZaakUrl) {
            Log::error('CreateDoorkomstZaken: failed to create deelzaak', [
                'hoofdzaak' => $hoofdZaak->url,
                'municipality' => $municipality->brk_identification,
            ]);

            return;
        }

        $this->copyZaakeigenschappen($deelConnection, $hoofdZaak, $newZaakUrl, $doorkomstZaaktype, $deelMapping);
        $this->createInitiator($deelConnectionName, $deelConnection, $newZaakUrl, $doorkomstZaaktype, $state, $initiator);
        $this->copyDocumenten($hoofdConnectionName, $deelConnectionName, $deelConnection, $hoofdZaak, $newZaakUrl, $doorkomstZaaktype);
        $this->createInitieleStatus($deelConnection, $newZaakUrl, $doorkomstZaaktype);

        $newOzZaak = ZaakReadModel::fromArray(ZgwResource::byUrl(
            $deelConnectionName,
            $newZaakUrl.'?expand=zaakobjecten,eigenschappen,status,status.statustype,rollen'
        ));

        $organisator = $this->resolveOrganisatorLabel();

        Zaak::updateOrCreate(
            ['zgw_zaak_url' => $newZaakUrl],
            [
                'public_id' => $newOzZaak->identificatie,
                'zaaktype_id' => $doorkomstZaaktype->id,
                'hoofdzaak_id' => $this->zaak->id, // local hoofdzaak link (works cross-instance)
                'zgw_zaaktype_url' => $newOzZaak->zaaktype, // snapshot of the version used
                'data_object_url' => null, // the Objects API is no longer part of the new flow
                'organisation_id' => $this->zaak->organisation_id,
                'organiser_user_id' => $this->zaak->organiser_user_id,
                'reference_data' => new ZaakReferenceData(
                    ...array_merge(
                        // Read back through the koppeling first: the deelzaak
                        // returns its eigenschappen under the namen its own
                        // catalogus uses, and a naam that is not translated back
                        // to its logical key is dropped by ZaakReferenceData.
                        $this->withEvenementDatesFromHoofdzaak(
                            ZaaktypeBlueprint::logicalEigenschappen($deelMapping, $newOzZaak->eigenschappen_key_value)
                        ),
                        [
                            'registratiedatum' => $newOzZaak->registratiedatum,
                            'status_name' => $newOzZaak->status_name ?? '',
                            'statustype_url' => $newOzZaak->statustype_url ?? '',
                            'organisator' => $organisator,
                        ]
                    )
                ),
                // Deelzaken carry the same snapshot so they can perform follow-up
                // actions on their own, without depending on the hoofdzaak.
                'form_state_snapshot' => $state->toSnapshot(),
            ]
        );
    }

    /**
     * Fill in the evenement dates for the deelzaak registration when the
     * doorkomst zaaktype does not carry the start_evenement/eind_evenement
     * eigenschappen (or the hoofdzaak had nothing to copy). The hoofdzaak
     * reference_data always holds them: it is built from the form state
     * ({@see MapFormStateToReferenceData}), not from ZGW.
     *
     * Eigenschappen that the deelzaak does have always win, so a complete
     * zaaktype produces exactly the same reference_data as before.
     *
     * @param  array<string, mixed>  $eigenschappen
     * @return array<string, mixed>
     */
    private function withEvenementDatesFromHoofdzaak(array $eigenschappen): array
    {
        foreach (['start_evenement', 'eind_evenement'] as $key) {
            if (($eigenschappen[$key] ?? '') !== '') {
                continue;
            }

            $fallback = $this->zaak->reference_data->{$key};

            if (is_string($fallback) && $fallback !== '') {
                $eigenschappen[$key] = $fallback;
            }
        }

        return $eigenschappen;
    }

    /**
     * The organisator label for the deelzaak, taken from the submitted aanvraag
     * by way of the local hoofdzaak. It is deliberately not derived from the
     * hoofdzaak ZGW rol: that rol's betrokkeneIdentificatie comes back empty on
     * instances that do not expose it (OneGround/RX Mission), which left the
     * organisator column and the export empty on every doorkomst zaak.
     *
     * The fallback mirrors MapFormStateToReferenceData::organisator() for
     * hoofdzaken whose reference_data predates that field.
     */
    private function resolveOrganisatorLabel(): string
    {
        $organisator = $this->zaak->reference_data->organisator;
        if (is_string($organisator) && $organisator !== '') {
            return $organisator;
        }

        $organisation = $this->zaak->organisation;
        if ($organisation && ! $organisation->isPersonal()) {
            return (string) $organisation->name;
        }

        return (string) ($this->zaak->organiserUser?->name);
    }

    /**
     * Copy the hoofdzaak eigenschappen onto the deelzaak.
     *
     * The two zaaktypen can have their own koppeling, so the same eigenschap can
     * carry a different naam on each side. Matching the hoofdzaak naam against
     * the deel catalogus directly therefore silently skips values whenever one
     * of the two renames. The naam is routed through the logical key instead:
     * hoofdzaak naam, logical key, deel naam.
     */
    private function copyZaakeigenschappen(ZgwConnection $deelConnection, ZaakReadModel $ozZaak, string $newZaakUrl, Zaaktype $doorkomstZaaktype, ?MunicipalityZaaktypeMapping $deelMapping): void
    {
        $newUuid = basename($newZaakUrl);
        $catalogi = $deelConnection->catalogi()->eigenschappen()->index(['zaaktype' => $doorkomstZaaktype->zgw_zaaktype_url])
            ->collect()
            ->map(fn ($e) => EigenschapData::from($e));

        $hoofdMapping = MunicipalityZaaktypeMapping::forZaaktype($this->zaak->zaaktype);
        $eigenschappen = ZaaktypeBlueprint::logicalEigenschappen($hoofdMapping, $ozZaak->eigenschappen_key_value);

        foreach ($eigenschappen as $logicalKey => $waarde) {
            if (! $waarde) {
                continue;
            }
            $cat = $catalogi->firstWhere('naam', ZaaktypeBlueprint::eigenschapNaam($deelMapping, (string) $logicalKey));
            if (! $cat) {
                continue;
            }
            $deelConnection->zaken()->zaken()->zaakeigenschappen($newUuid)->store([
                'zaak' => $newZaakUrl,
                'eigenschap' => (string) $cat->url,
                'waarde' => $waarde,
            ]);
        }
    }

    /**
     * Register the initiator on the deelzaak from the form's aanvrager data,
     * mirroring the hoofdzaak initiator ({@see UpdateInitiatorZGW}).
     * The hoofdzaak ZGW rol is deliberately not copied: its betrokkeneIdentificatie
     * is empty and its betrokkene url points at the source instance.
     *
     * @param  array<string, mixed>  $initiator  output of ZaakeigenschappenMap::buildInitiator()
     */
    private function createInitiator(string $deelConnectionName, ZgwConnection $deelConnection, string $newZaakUrl, Zaaktype $doorkomstZaaktype, FormState $state, array $initiator): void
    {
        if ($initiator === []) {
            return;
        }

        $roltypen = $deelConnection->catalogi()->roltypen()->index(['zaaktype' => $doorkomstZaaktype->zgw_zaaktype_url]);
        $mapping = MunicipalityZaaktypeMapping::forZaaktype($doorkomstZaaktype);
        $roltype = ZaaktypeBlueprint::initiatorRoltype($mapping, $roltypen);
        if (! $roltype) {
            Log::warning('CreateDoorkomstZaken: no initiator roltype', ['zaak' => $newZaakUrl]);

            return;
        }

        $rolData = InitiatorRolBuilder::build(
            $deelConnectionName,
            $newZaakUrl,
            $roltype['url'],
            $state,
            $initiator,
            InitiatorRolBuilder::anpIdentificatieForUser($this->zaak->organiser_user_id),
        );
        if ($rolData === null) {
            return;
        }

        $deelConnection->zaken()->rollen()->store($rolData);
    }

    private function copyDocumenten(string $hoofdConnectionName, string $deelConnectionName, ZgwConnection $deelConnection, ZaakReadModel $ozZaak, string $newZaakUrl, Zaaktype $doorkomstZaaktype): void
    {
        // Same instance: the informatieobject url is directly linkable. Cross
        // instance: the url lives in the hoofdzaak's documenten API, which the
        // target does not know, so each document is downloaded and re-created in the
        // target documenten API before linking (see copyDocumentToTargetInstance).
        $sameInstance = $deelConnectionName === $hoofdConnectionName;

        // Resolving a target informatieobjecttype is only needed cross-instance; the
        // source type url is not portable and must be re-mapped by omschrijving. The
        // mapping and the source-type omschrijvingen are resolved at most once here.
        $deelMapping = $sameInstance ? null : MunicipalityZaaktypeMapping::forZaaktype($doorkomstZaaktype);
        $sourceTypeOmschrijvingen = [];

        $zios = Zgw::connection($hoofdConnectionName)->zaken()->zaakinformatieobjecten()->index(['zaak' => $ozZaak->url]);
        foreach ($zios as $zio) {
            $informatieobjectUrl = Arr::get($zio, 'informatieobject');
            if (! $informatieobjectUrl) {
                continue;
            }

            if ($sameInstance) {
                $deelConnection->zaken()->zaakinformatieobjecten()->store([
                    'zaak' => $newZaakUrl,
                    'informatieobject' => $informatieobjectUrl,
                ]);

                continue;
            }

            // A single failing document is logged and skipped so the remaining
            // documents and the deelzaak's status/local record are still created:
            // re-running the job would create a duplicate ZGW deelzaak (the
            // idempotency check is local), so aborting here is worse than losing
            // one document, which can be re-added by hand.
            try {
                $targetUrl = $this->copyDocumentToTargetInstance(
                    $hoofdConnectionName,
                    $deelConnectionName,
                    $deelConnection,
                    (string) $informatieobjectUrl,
                    $ozZaak->bronorganisatie,
                    $doorkomstZaaktype,
                    $deelMapping,
                    $sourceTypeOmschrijvingen,
                );
            } catch (Throwable $e) {
                Log::error('CreateDoorkomstZaken: failed to copy document to target instance', [
                    'zaak' => $newZaakUrl,
                    'informatieobject' => $informatieobjectUrl,
                    'exception' => $e->getMessage(),
                ]);

                continue;
            }

            if ($targetUrl === null) {
                continue;
            }

            $deelConnection->zaken()->zaakinformatieobjecten()->store([
                'zaak' => $newZaakUrl,
                'informatieobject' => $targetUrl,
            ]);
        }
    }

    /**
     * Download an enkelvoudiginformatieobject from the hoofdzaak's documenten API
     * and re-create it in the deelzaak's instance. Returns the new EIO url, or null
     * when no target informatieobjecttype could be resolved (document then skipped).
     *
     * @param  array<string, string>  $sourceTypeOmschrijvingen  memo: source type url => omschrijving
     */
    private function copyDocumentToTargetInstance(
        string $hoofdConnectionName,
        string $deelConnectionName,
        ZgwConnection $deelConnection,
        string $informatieobjectUrl,
        string $bronorganisatie,
        Zaaktype $doorkomstZaaktype,
        ?MunicipalityZaaktypeMapping $deelMapping,
        array &$sourceTypeOmschrijvingen,
    ): ?string {
        $eio = ZgwResource::byUrl($hoofdConnectionName, $informatieobjectUrl);

        $targetType = $this->resolveTargetInformatieobjecttype(
            $hoofdConnectionName,
            (string) ($eio['informatieobjecttype'] ?? ''),
            $doorkomstZaaktype,
            $deelMapping,
            $sourceTypeOmschrijvingen,
        );
        if ($targetType === null) {
            Log::warning('CreateDoorkomstZaken: no target informatieobjecttype for document copy', [
                'informatieobject' => $informatieobjectUrl,
                'zaaktype' => $doorkomstZaaktype->zgw_zaaktype_url,
            ]);

            return null;
        }

        $content = ZgwResource::downloadDocument($hoofdConnectionName, (string) ($eio['uuid'] ?? ''));

        $payload = [
            'bronorganisatie' => $bronorganisatie,
            'creatiedatum' => $eio['creatiedatum'] ?? now()->format('Y-m-d'),
            // Determined by the target connection, not copied from the source: the
            // source instance's confidentiality scheme need not match the target's,
            // so a copied value can be wrong on the deel connection.
            'vertrouwelijkheidaanduiding' => ZgwConnectionConfig::systemUploadDefault($deelConnectionName),
            'titel' => $eio['titel'] ?? ($eio['bestandsnaam'] ?? 'Document'),
            'auteur' => $eio['auteur'] ?? 'Onbekend',
            'taal' => $eio['taal'] ?? 'dut',
            'bestandsnaam' => $eio['bestandsnaam'] ?? '',
            'bestandsomvang' => strlen($content),
            'formaat' => ($eio['formaat'] ?? '') ?: 'application/octet-stream',
            'inhoud' => base64_encode($content),
            'informatieobjecttype' => $targetType,
            'indicatieGebruiksrecht' => false,
        ];

        // Preserve draft/definitief and the description when the source carries them.
        if (! empty($eio['status'])) {
            $payload['status'] = $eio['status'];
        }
        if (! empty($eio['beschrijving'])) {
            $payload['beschrijving'] = $eio['beschrijving'];
        }

        $response = $deelConnection->documenten()->enkelvoudiginformatieobjecten()->store($payload);

        $newUrl = $response['url'] ?? null;
        if (! $newUrl) {
            Log::error('CreateDoorkomstZaken: target documenten store returned no url', [
                'informatieobject' => $informatieobjectUrl,
            ]);

            return null;
        }

        return (string) $newUrl;
    }

    /**
     * Resolve the target-instance informatieobjecttype url for a copied document.
     * The source type url is not portable across instances, so match by omschrijving:
     * an exact omschrijving match on the deelzaaktype's types, else the aanvraag or
     * bijlage blueprint slot, else null.
     *
     * @param  array<string, string>  $sourceTypeOmschrijvingen  memo: source type url => omschrijving
     */
    private function resolveTargetInformatieobjecttype(
        string $hoofdConnectionName,
        string $sourceTypeValue,
        Zaaktype $doorkomstZaaktype,
        ?MunicipalityZaaktypeMapping $deelMapping,
        array &$sourceTypeOmschrijvingen,
    ): ?string {
        $types = $doorkomstZaaktype->documentTypesForUser();
        if ($types->isEmpty()) {
            return null;
        }

        $sourceOmschrijving = $this->sourceTypeOmschrijving($hoofdConnectionName, $sourceTypeValue, $sourceTypeOmschrijvingen);

        if ($sourceOmschrijving !== '') {
            $exact = $types->first(fn ($type) => property_exists($type, 'omschrijving') && $type->omschrijving === $sourceOmschrijving);
            if ($exact) {
                return (string) $exact->url;
            }
        }

        // The aanvraag PDF maps to the deelzaaktype's aanvraag slot; anything else is
        // treated as a bijlage.
        $isAanvraag = $sourceOmschrijving !== '' && $sourceOmschrijving === $this->hoofdAanvraagOmschrijving();
        $target = $isAanvraag
            ? ZaaktypeBlueprint::aanvraagInformatieobjecttype($deelMapping, $types)
            : ZaaktypeBlueprint::bijlageInformatieobjecttype($deelMapping, $types);

        return $target ? (string) $target->url : null;
    }

    /**
     * The omschrijving of a source informatieobjecttype value: a followable url is
     * fetched (and memoized), an inline value is the omschrijving itself.
     *
     * @param  array<string, string>  $memo  source type url => omschrijving
     */
    private function sourceTypeOmschrijving(string $hoofdConnectionName, string $value, array &$memo): string
    {
        if ($value === '') {
            return '';
        }
        if (! str_starts_with($value, 'http')) {
            return $value;
        }
        if (array_key_exists($value, $memo)) {
            return $memo[$value];
        }

        $type = ZgwResource::byUrl($hoofdConnectionName, $value);

        return $memo[$value] = (string) ($type['omschrijving'] ?? '');
    }

    /**
     * The omschrijving of the hoofdzaak's aanvraag informatieobjecttype, resolved
     * from its own zaaktype blueprint and memoized for the whole job run.
     */
    private function hoofdAanvraagOmschrijving(): ?string
    {
        if ($this->hoofdAanvraagResolved) {
            return $this->hoofdAanvraagOmschrijving;
        }
        $this->hoofdAanvraagResolved = true;

        $zaaktype = $this->zaak->zaaktype;
        if (! $zaaktype) {
            return $this->hoofdAanvraagOmschrijving = null;
        }

        $mapping = MunicipalityZaaktypeMapping::forZaaktype($zaaktype);
        $aanvraag = ZaaktypeBlueprint::aanvraagInformatieobjecttype($mapping, $zaaktype->documentTypesForUser());

        return $this->hoofdAanvraagOmschrijving = ($aanvraag && property_exists($aanvraag, 'omschrijving'))
            ? $aanvraag->omschrijving
            : null;
    }

    private function createInitieleStatus(ZgwConnection $deelConnection, string $newZaakUrl, Zaaktype $doorkomstZaaktype): void
    {
        $statustypen = $deelConnection->catalogi()->statustypen()->index(['zaaktype' => $doorkomstZaaktype->zgw_zaaktype_url])->collect();
        $mapping = MunicipalityZaaktypeMapping::forZaaktype($doorkomstZaaktype);
        $initieel = ZaaktypeBlueprint::initialStatustype($mapping, $statustypen);
        if (! $initieel) {
            Log::warning('CreateDoorkomstZaken: no statustype', ['zaak' => $newZaakUrl]);

            return;
        }

        $deelConnection->zaken()->statussen()->store([
            'zaak' => $newZaakUrl,
            'statustype' => $initieel['url'],
            'datumStatusGezet' => now()->toIso8601String(),
        ]);
    }
}
