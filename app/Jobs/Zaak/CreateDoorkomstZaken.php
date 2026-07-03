<?php

declare(strict_types=1);

namespace App\Jobs\Zaak;

use App\Actions\Geospatial\CheckIntersects;
use App\EventForm\State\FormState;
use App\EventForm\Submit\ZaakeigenschappenMap;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Normalizers\OpenFormsNormalizer;
use App\Services\Zgw\InitiatorRolBuilder;
use App\Services\Zgw\ZaakReadModel;
use App\Services\Zgw\ZaaktypeBlueprint;
use App\Services\Zgw\ZgwConnectionConfig;
use App\Services\Zgw\ZgwResource;
use App\Support\Helpers\ArrayHelper;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Brick\Geo\Engine\PdoEngine;
use Brick\Geo\Io\GeoJsonReader;
use Brick\Geo\LineString;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Woweb\Zgw\Connection\ZgwConnection;
use Woweb\Zgw\Data\Generated\Catalogi\EigenschapData;
use Woweb\Zgw\Facades\Zgw;

/**
 * Voor route-events (zaaktype met `triggers_route_check = true`): maakt
 * per gemeente waar de route doorheen loopt (exclusief start- en
 * eindgemeente) een "doorkomst"-deelzaak aan en kopieert relevante
 * eigenschappen / initiator / documenten / initiële status.
 *
 * Input is nu `Zaak`; de LineString komt uit `form_state_snapshot`
 * via `ZaakeigenschappenMap::buildEventLocation()`.
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
        $lineArray = $this->extractLineArray($map->buildEventLocation($state));
        if (! $lineArray) {
            return;
        }

        /** @var LineString $line */
        $line = (new GeoJsonReader)->read((string) json_encode($lineArray));

        $engine = new PdoEngine(DB::connection()->getPdo());
        $checkIntersects = new CheckIntersects($engine);

        $all = $checkIntersects->checkIntersectsWithModels($line);
        $startModels = $checkIntersects->checkIntersectsWithModels($line->startPoint());
        $endModels = $checkIntersects->checkIntersectsWithModels($line->endPoint());

        $excluded = $startModels->pluck('brk_identification')
            ->merge($endModels->pluck('brk_identification'))
            ->unique()
            ->toArray();

        $hoofdZaakMuniBrk = $this->zaak->municipality?->brk_identification;

        $passing = $all->reject(fn ($m) => in_array($m->brk_identification, $excluded, true))
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
     * @param  array<string, mixed>  $eventLocation
     * @return array<string, mixed>|null
     */
    private function extractLineArray(array $eventLocation): ?array
    {
        $line = $eventLocation['line'] ?? null;
        if (! $line || $line === 'None') {
            return null;
        }

        $json = is_array($line) ? json_encode($line) : OpenFormsNormalizer::normalizeGeoJson($line);
        $decoded = json_decode((string) $json, true);
        if (! is_array($decoded)) {
            return null;
        }

        return ArrayHelper::findElementWithKey($decoded, 'coordinates');
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

        $this->copyZaakeigenschappen($deelConnection, $hoofdZaak, $newZaakUrl, $doorkomstZaaktype);
        $this->createInitiator($deelConnection, $newZaakUrl, $doorkomstZaaktype, $state, $initiator);
        $this->copyDocumenten($hoofdConnectionName, $deelConnectionName, $deelConnection, $hoofdZaak, $newZaakUrl, $doorkomstZaaktype);
        $this->createInitieleStatus($deelConnection, $newZaakUrl, $doorkomstZaaktype);

        $newOzZaak = ZaakReadModel::fromArray(ZgwResource::byUrl(
            $deelConnectionName,
            $newZaakUrl.'?expand=zaakobjecten,eigenschappen,status,status.statustype,rollen'
        ));

        $organisator = $this->resolveOrganisatorLabel($hoofdZaak);

        Zaak::updateOrCreate(
            ['zgw_zaak_url' => $newZaakUrl],
            [
                'public_id' => $newOzZaak->identificatie,
                'zaaktype_id' => $doorkomstZaaktype->id,
                'hoofdzaak_id' => $this->zaak->id, // local hoofdzaak link (works cross-instance)
                'zgw_zaaktype_url' => $newOzZaak->zaaktype, // snapshot of the version used
                'data_object_url' => null, // Objects API is in nieuwe flow weg
                'organisation_id' => $this->zaak->organisation_id,
                'organiser_user_id' => $this->zaak->organiser_user_id,
                'reference_data' => new ZaakReferenceData(
                    ...array_merge(
                        $newOzZaak->eigenschappen_key_value,
                        [
                            'registratiedatum' => $newOzZaak->registratiedatum,
                            'status_name' => $newOzZaak->status_name ?? '',
                            'statustype_url' => $newOzZaak->statustype_url ?? '',
                            'organisator' => $organisator,
                        ]
                    )
                ),
                // Deelzaken krijgen dezelfde snapshot mee zodat ze zelfstandig
                // vervolg-acties kunnen doen zonder van de hoofdzaak af te
                // hangen.
                'form_state_snapshot' => $state->toSnapshot(),
            ]
        );
    }

    private function resolveOrganisatorLabel(ZaakReadModel $ozZaak): string
    {
        $initiator = $ozZaak->initiator;
        if (! $initiator) {
            return '';
        }

        $type = $initiator['betrokkeneType'] ?? null;
        $id = $initiator['betrokkeneIdentificatie'] ?? [];

        if ($type === 'natuurlijk_persoon') {
            return trim(($id['voornamen'] ?? '').' '.($id['geslachtsnaam'] ?? ''));
        }

        if ($type === 'niet_natuurlijk_persoon') {
            $contactNaam = $initiator['contactpersoonRol']['naam'] ?? '';

            return trim(($id['statutaireNaam'] ?? '').' - '.$contactNaam);
        }

        return '';
    }

    private function copyZaakeigenschappen(ZgwConnection $deelConnection, ZaakReadModel $ozZaak, string $newZaakUrl, Zaaktype $doorkomstZaaktype): void
    {
        $newUuid = basename($newZaakUrl);
        $catalogi = $deelConnection->catalogi()->eigenschappen()->index(['zaaktype' => $doorkomstZaaktype->zgw_zaaktype_url])
            ->collect()
            ->map(fn ($e) => EigenschapData::from($e));

        foreach ($ozZaak->eigenschappen_key_value as $naam => $waarde) {
            if (! $waarde) {
                continue;
            }
            $cat = $catalogi->firstWhere('naam', $naam);
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
    private function createInitiator(ZgwConnection $deelConnection, string $newZaakUrl, Zaaktype $doorkomstZaaktype, FormState $state, array $initiator): void
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

        $rolData = InitiatorRolBuilder::build($newZaakUrl, $roltype['url'], $state, $initiator);
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
