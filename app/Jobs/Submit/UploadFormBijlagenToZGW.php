<?php

declare(strict_types=1);

namespace App\Jobs\Submit;

use App\EventForm\Schema\FileUploadFields;
use App\Exceptions\FormAttachmentUploadFailedException;
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
use Spatie\Activitylog\Models\Activity;
use Throwable;
use Woweb\Zgw\Connection\ZgwConnection;
use Woweb\Zgw\Facades\Zgw;

/**
 * Uploads every file the organiser attached through a file upload field of
 * the form as a zaakinformatieobject. Per file: one POST to
 * enkelvoudiginformatieobjecten and one POST to zaakinformatieobjecten (the
 * same pattern as UploadDocumentAction).
 *
 * The file upload fields come from {@see FileUploadFields}, so a file upload
 * that is added to the form is picked up by this job automatically.
 *
 * Every file is uploaded on its own: a file the API refuses does not stop the
 * other files. A file that was uploaded is recorded in the activity log with
 * its storage path, so a retry of this job skips it instead of adding the
 * same document to the case a second time.
 */
final class UploadFormBijlagenToZGW implements ShouldQueue
{
    use Queueable;

    /**
     * Seconds to wait before the second and third attempt. A retry only
     * happens after a temporary failure, so give the API some time.
     *
     * @var list<int>
     */
    public array $backoff = [30, 120];

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

        // Build a flat map: path => original file name.
        // `storeFileNamesIn("{key}_namen")` writes the original name into the
        // snapshot once the file is stored:
        //   - single field:   values["{key}_namen"] = 'Plan A.pdf'
        //   - multiple field: values["{key}_namen"] = {path: 'Bijlage.pdf', …}
        // When the names are missing (older snapshots with preserveFilenames)
        // it falls back to basename() of the path.
        /** @var array<string, string> $bestandPaden  path => original name */
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

        // The values come unvalidated from form_state_snapshot: the submit
        // flow dehydrates via getStateSnapshot() and so runs no FileUpload
        // validation. An organiser could therefore inject an arbitrary path
        // into the state that points at the upload folder of another
        // organisation. Two checks are enforced here:
        //   1. Containment: the path must be inside the upload folder of THIS
        //      zaak's organisation (event-form-uploads/<org_uuid>/...).
        //   2. File type: the MIME type must be on the document allowlist
        //      (blocks executables and scripts, among others), the check that
        //      would normally have run in the form request.
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        $toegestanePrefix = sprintf('event-form-uploads/%s/', $this->zaak->organisation?->uuid);
        /** @var array<string, string> $aanwezig  path => original name */
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

        // Skip the files an earlier attempt of this job already uploaded.
        $aanwezig = array_diff_key($aanwezig, $this->alreadyUploadedPaths());
        if ($aanwezig === []) {
            return;
        }

        $informatieobjecttype = $this->resolveInformatieobjecttype();
        $connectionName = $this->zaak->zgwConnectionName();
        $connection = Zgw::connection($connectionName);
        $bronorganisatie = $this->zaak->openzaak->bronorganisatie;
        $auteur = $this->zaak->organiserUser->name ?? 'Organisator';

        /** @var list<FormAttachmentUploadFailedException> $permanentFailures */
        $permanentFailures = [];
        /** @var list<FormAttachmentUploadFailedException> $temporaryFailures */
        $temporaryFailures = [];

        foreach ($aanwezig as $pad => $bestandsnaam) {
            try {
                $info = $this->uploadFile($connection, $connectionName, $pad, $bestandsnaam, $informatieobjecttype, $bronorganisatie, $auteur, $disk);
            } catch (FormAttachmentUploadFailedException $e) {
                // Report each refused file with its own context, then move on
                // to the next file.
                report($e);
                if ($e->isPermanent()) {
                    $permanentFailures[] = $e;
                } else {
                    $temporaryFailures[] = $e;
                }

                continue;
            }

            activity('document')
                ->event('created')
                ->causedBy($this->zaak->organiserUser)
                ->performedOn($this->zaak)
                ->withProperties([
                    'document_uuid' => $info->uuid,
                    'filename' => $info->bestandsnaam,
                    'titel' => $info->titel,
                    // Lets a retry of this job recognise the file as uploaded.
                    'source_path' => $pad,
                ])
                ->log(__('activity/event.created'));
        }

        $this->zaak->clearZgwCache();

        if ($temporaryFailures !== []) {
            // Fail this attempt so the queue retries it. The files uploaded
            // above are skipped on that retry.
            throw new RuntimeException(sprintf(
                '%d of %d form attachment(s) for zaak %s failed with a temporary error; the job is retried for those files.',
                count($temporaryFailures),
                count($aanwezig),
                $this->zaak->id,
            ), 0, $temporaryFailures[0]);
        }

        if ($permanentFailures !== []) {
            // The API refuses these files as they are, so a retry would fail
            // the same way. Mark the job as failed without further attempts:
            // it then shows up in the failed jobs, and once the cause (for
            // example the document type of the connection) is fixed, a manual
            // retry uploads only the files that are still missing.
            $this->fail($permanentFailures[0]);
        }
    }

    /**
     * Stores one file as a document and links it to the zaak.
     *
     * @throws FormAttachmentUploadFailedException
     */
    private function uploadFile(
        ZgwConnection $connection,
        string $connectionName,
        string $pad,
        string $bestandsnaam,
        string $informatieobjecttype,
        ?string $bronorganisatie,
        string $auteur,
        FilesystemAdapter $disk,
    ): Informatieobject {
        try {
            $content = (string) $disk->get($pad);

            $info = new Informatieobject(...ZgwResource::ensureUuid($connection->documenten()->enkelvoudiginformatieobjecten()->store([
                'bronorganisatie' => $bronorganisatie,
                'creatiedatum' => now()->format('Y-m-d'),
                'vertrouwelijkheidaanduiding' => ZgwConnectionConfig::systemUploadDefault($connectionName),
                'titel' => $bestandsnaam,
                'auteur' => $auteur,
                'taal' => 'dut',
                'bestandsnaam' => $bestandsnaam,
                'bestandsomvang' => strlen($content),
                'formaat' => DocumentUploadType::determineFormaat($pad, $bestandsnaam) ?: 'application/octet-stream',
                'inhoud' => base64_encode($content),
                'informatieobjecttype' => $informatieobjecttype,
                'indicatieGebruiksrecht' => false,
                'status' => Informatieobject::STATUS_DEFINITIEF,
            ])));
        } catch (Throwable $e) {
            throw FormAttachmentUploadFailedException::fromFailure(
                $e, (string) $this->zaak->id, $connectionName, FormAttachmentUploadFailedException::STEP_DOCUMENT,
            );
        }

        try {
            $connection->zaken()->zaakinformatieobjecten()->store([
                'zaak' => $this->zaak->zgw_zaak_url,
                'informatieobject' => $info->url,
            ]);
        } catch (Throwable $e) {
            $failure = FormAttachmentUploadFailedException::fromFailure(
                $e, (string) $this->zaak->id, $connectionName, FormAttachmentUploadFailedException::STEP_LINK,
            );

            // A refused link was certainly not created. After a time-out or a
            // server error it may have been created all the same, so ask the
            // API before treating the link as missing.
            $linked = $failure->isPermanent() ? false : $this->isLinked($connection, $info);

            if ($linked === true) {
                return $info;
            }

            // Without the link the document is not part of the case, so
            // remove it before a retry creates a new one. When it is unknown
            // whether the link exists, keep the document: a loose copy is
            // better than removing a document the case refers to.
            if ($linked === false) {
                $this->deleteUnlinkedDocument($connection, $info);
            }

            throw $failure;
        }

        return $info;
    }

    /**
     * Whether the document is linked to this zaak, or null when the API
     * cannot tell.
     */
    private function isLinked(ZgwConnection $connection, Informatieobject $info): ?bool
    {
        try {
            // Match on both urls instead of trusting the filter: a backend
            // that ignores a query parameter would otherwise report another
            // document's link as this one.
            return $connection->zaken()->zaakinformatieobjecten()->index([
                'zaak' => $this->zaak->zgw_zaak_url,
                'informatieobject' => $info->url,
            ])->contains(fn (array $link): bool => ($link['informatieobject'] ?? null) === $info->url
                && ($link['zaak'] ?? null) === $this->zaak->zgw_zaak_url);
        } catch (Throwable) {
            return null;
        }
    }

    private function deleteUnlinkedDocument(ZgwConnection $connection, Informatieobject $info): void
    {
        // Take the uuid from the url the document was created at, not from
        // the response body, so this can only ever remove that document.
        $uuid = basename((string) parse_url($info->url, PHP_URL_PATH));

        try {
            $connection->documenten()->enkelvoudiginformatieobjecten()->delete($uuid);
        } catch (Throwable) {
            Log::warning('UploadFormBijlagenToZGW: could not remove an unlinked document after a failed link', [
                'zaak_id' => $this->zaak->id,
                'document_uuid' => $uuid,
            ]);
        }
    }

    /**
     * Storage paths of the files an earlier attempt of this job already
     * uploaded for this zaak, read from the activity log.
     *
     * @return array<string, bool>
     */
    private function alreadyUploadedPaths(): array
    {
        /** @var list<string> $paths */
        $paths = Activity::query()
            ->where('log_name', 'document')
            ->where('event', 'created')
            ->where('subject_type', $this->zaak->getMorphClass())
            ->where('subject_id', $this->zaak->getKey())
            ->get()
            ->map(fn (Activity $activity) => data_get($activity->properties, 'source_path'))
            ->filter(fn ($pad): bool => is_string($pad) && $pad !== '')
            ->values()
            ->all();

        return array_fill_keys($paths, true);
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
