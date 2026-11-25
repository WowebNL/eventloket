<?php

declare(strict_types=1);

namespace App\Jobs\Submit;

use App\EventForm\Schema\FileUploadFields;
use App\Models\Zaak;
use App\Services\Zgw\ZaaktypeBlueprint;
use App\Services\Zgw\ZgwConnectionConfig;
use App\Services\Zgw\ZgwResource;
use App\Support\Uploads\DocumentUploadType;
use App\ValueObjects\ZGW\Informatieobject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Woweb\Zgw\Facades\Zgw;

/**
 * Uploads every file the organiser attached through a file upload field of
 * the form as a zaakinformatieobject. Per file: one POST to
 * enkelvoudiginformatieobjecten and one POST to zaakinformatieobjecten (the
 * same pattern as UploadDocumentAction).
 *
 * The file upload fields come from {@see FileUploadFields}, so a file upload
 * that is added to the form is picked up by this job automatically.
 */
final class UploadFormBijlagenToZGW implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Zaak $zaak) {}

    public function handle(): void
    {
        if (! $this->zaak->zgw_zaak_url) {
            Log::warning('UploadFormBijlagenToZGW: zaak heeft geen ZGW-url', [
                'zaak_id' => $this->zaak->id,
            ]);

            return;
        }

        $values = $this->zaak->form_state_snapshot['values'] ?? [];
        if (! is_array($values) || $values === []) {
            return;
        }

        // Bouw een platte map: pad → originele bestandsnaam.
        // `storeFileNamesIn("{key}_namen")` schrijft na het opslaan de
        // originele naam automatisch in de snapshot:
        //   - enkelvoudig veld: values["{key}_namen"] = 'Plan A.pdf'
        //   - meervoudig veld:  values["{key}_namen"] = {pad: 'Bijlage.pdf', …}
        // Bij ontbrekende namen (oudere snapshots met preserveFilenames) valt
        // 't terug op basename() van het pad.
        /** @var array<string, string> $bestandPaden  pad => originele naam */
        $bestandPaden = [];
        foreach (FileUploadFields::keys() as $key) {
            $value = $values[$key] ?? null;
            $namen = $values["{$key}_namen"] ?? null;

            if (is_string($value) && $value !== '') {
                $bestandPaden[$value] = is_string($namen) && $namen !== '' ? $namen : basename($value);

                continue;
            }
            if (is_array($value)) {
                foreach ($value as $entry) {
                    if (is_string($entry) && $entry !== '') {
                        $origineleNaam = is_array($namen) && isset($namen[$entry]) && is_string($namen[$entry])
                            ? $namen[$entry]
                            : basename($entry);
                        $bestandPaden[$entry] = $origineleNaam;
                    }
                }
            }
        }

        if ($bestandPaden === []) {
            return;
        }

        // De waarden komen ongevalideerd uit form_state_snapshot: de submit-
        // flow dehydrateert via getStateSnapshot() en draait dus géén
        // FileUpload-validatie. Een organisator kan daardoor een willekeurig
        // pad in de state injecteren dat naar de upload-map van een andere
        // organisatie wijst. We dwingen hier daarom twee checks af:
        //   1. Containment: het pad moet binnen de upload-map van DEZE zaak
        //      z'n organisatie liggen (event-form-uploads/<org_id>/...).
        //   2. Bestandstype: het MIME-type moet op de document-allowlist staan
        //      (blokkeert o.a. executables/scripts) — de check die normaal in
        //      de form-request had moeten draaien.
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        $toegestanePrefix = sprintf('event-form-uploads/%s/', $this->zaak->organisation?->uuid);
        /** @var array<string, string> $aanwezig  pad => originele naam */
        $aanwezig = [];
        foreach ($bestandPaden as $pad => $origineleNaam) {
            if (str_contains($pad, '..') || ! str_starts_with($pad, $toegestanePrefix)) {
                Log::critical('UploadFormBijlagenToZGW: bijlage-pad buiten de eigen upload-map geweigerd', [
                    'zaak_id' => $this->zaak->id,
                    'organisation_uuid' => $this->zaak->organisation?->uuid,
                    'pad' => $pad,
                ]);

                continue;
            }
            if (! $disk->exists($pad)) {
                Log::warning('UploadFormBijlagenToZGW: bijlage ontbreekt op disk', [
                    'zaak_id' => $this->zaak->id,
                    'pad' => $pad,
                ]);

                continue;
            }
            $mime = (string) $disk->mimeType($pad);
            if (! DocumentUploadType::storedFileIsAllowed($disk->path($pad), $mime, $origineleNaam)) {
                Log::warning('UploadFormBijlagenToZGW: bijlage met niet-toegestaan bestandstype geweigerd', [
                    'zaak_id' => $this->zaak->id,
                    'pad' => $pad,
                    'mime' => $mime,
                ]);

                continue;
            }
            $aanwezig[$pad] = $origineleNaam;
        }
        if ($aanwezig === []) {
            return;
        }

        $informatieobjecttype = $this->resolveInformatieobjecttype();
        $connectionName = $this->zaak->zgwConnectionName();
        $connection = Zgw::connection($connectionName);

        foreach ($aanwezig as $pad => $bestandsnaam) {
            $content = (string) $disk->get($pad);

            $info = new Informatieobject(...ZgwResource::ensureUuid($connection->documenten()->enkelvoudiginformatieobjecten()->store([
                'bronorganisatie' => $this->zaak->openzaak->bronorganisatie,
                'creatiedatum' => now()->format('Y-m-d'),
                'vertrouwelijkheidaanduiding' => ZgwConnectionConfig::systemUploadDefault($connectionName),
                'titel' => $bestandsnaam,
                'auteur' => $this->zaak->organiserUser->name ?? 'Organisator',
                'taal' => 'dut',
                'bestandsnaam' => $bestandsnaam,
                'bestandsomvang' => strlen($content),
                'formaat' => DocumentUploadType::determineFormaat($pad, $bestandsnaam) ?: 'application/octet-stream',
                'inhoud' => base64_encode($content),
                'informatieobjecttype' => $informatieobjecttype,
                'indicatieGebruiksrecht' => false,
                'status' => Informatieobject::STATUS_DEFINITIEF,
            ])));

            $connection->zaken()->zaakinformatieobjecten()->store([
                'zaak' => $this->zaak->zgw_zaak_url,
                'informatieobject' => $info->url,
            ]);

            activity('document')
                ->event('created')
                ->causedBy($this->zaak->organiserUser)
                ->performedOn($this->zaak)
                ->withProperties([
                    'document_uuid' => $info->uuid,
                    'filename' => $info->bestandsnaam,
                    'titel' => $info->titel,
                ])
                ->log(__('activity/event.created'));
        }

        $this->zaak->clearZgwCache();
    }

    /**
     * Resolve the informatieobjecttype for attachments via the blueprint:
     * the mapped bijlage-type when configured, otherwise the type whose
     * omschrijving contains "bijlage" (case-insensitive), otherwise the first.
     */
    private function resolveInformatieobjecttype(): string
    {
        $types = $this->zaak->document_types;
        if (! $types || $types->isEmpty()) {
            throw new RuntimeException(
                'Geen informatieobjecttype gevonden voor zaaktype '
                .($this->zaak->zaaktype->id ?? '?')
                .' — kan bijlagen niet uploaden.'
            );
        }

        $mapping = $this->zaak->zaaktypeMapping();
        $chosen = ZaaktypeBlueprint::bijlageInformatieobjecttype($mapping, $types);

        if (! $chosen || ! $chosen->url) {
            throw new RuntimeException(
                'Geen informatieobjecttype gevonden voor zaaktype '
                .($this->zaak->zaaktype->id ?? '?')
                .' — kan bijlagen niet uploaden.'
            );
        }

        return (string) $chosen->url;
    }
}
