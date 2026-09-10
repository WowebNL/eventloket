<?php

declare(strict_types=1);

namespace App\Jobs\Zaak;

use App\Models\User;
use App\Models\Zaak;
use App\Notifications\DocumentsZipReady;
use App\Services\Zgw\ZgwResource;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

final class CreateDocumentsZipJob implements ShouldQueue
{
    use Queueable;

    /**
     * The file names of the selected documents, keyed by uuid, as they were
     * shown when the selection was made.
     *
     * A document that is no longer on the zaak by the time the archive is built
     * cannot be named from the zaak any more, and it has to be named: a reader
     * can do nothing with an identifier. The name therefore travels along from
     * the selection. Declared with a default instead of promoted, so a job that
     * was queued before this property existed still unserializes.
     *
     * @var array<string, string>
     */
    public array $selectedNames = [];

    /**
     * @param  array<int, string>  $documentUuids
     * @param  array<string, string>  $selectedNames
     */
    public function __construct(
        public readonly Zaak $zaak,
        public readonly array $documentUuids,
        public readonly int $userId,
        array $selectedNames = [],
    ) {
        $this->selectedNames = $selectedNames;
    }

    public function handle(): void
    {
        $user = User::find($this->userId);

        $token = self::buildZip($this->zaak, $this->documentUuids, $this->userId, $this->selectedNames);

        if ($token === null) {
            Log::error('CreateDocumentsZipJob: zip aanmaken mislukt', [
                'zaak_id' => $this->zaak->id,
                'user_id' => $this->userId,
            ]);

            return;
        }

        $user?->notify(new DocumentsZipReady($this->zaak, $token, count($this->documentUuids)));

        activity('document')
            ->event('multi_download')
            ->causedBy($user)
            ->performedOn($this->zaak)
            ->withProperties(['count' => count($this->documentUuids)])
            ->log(__('activity/event.multi_download', ['count' => count($this->documentUuids)]));
    }

    /**
     * Creates the zip file in private storage and caches a token to retrieve it.
     * Returns the token on success, or null on failure.
     *
     * @param  array<int, string>  $documentUuids
     * @param  array<string, string>  $selectedNames  file names keyed by uuid, from the selection
     */
    public static function buildZip(Zaak $zaak, array $documentUuids, int $userId, array $selectedNames = []): ?string
    {
        $connectionName = $zaak->zgwConnectionName();
        $token = (string) Str::uuid();
        $zipPath = storage_path("app/private/zips/{$token}.zip");

        if (! is_dir(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $usedNames = [];
        $unretrievable = [];
        $gone = [];

        foreach ($documentUuids as $uuid) {
            $document = $zaak->documenten->where('uuid', $uuid)->first();

            if ($document === null) {
                Log::warning('CreateDocumentsZipJob: document niet gevonden', [
                    'zaak_id' => $zaak->id,
                    'uuid' => $uuid,
                ]);

                $gone[] = $selectedNames[$uuid]
                    ?? (string) __('shared/actions.download_documents.missing.unnamed');

                continue;
            }

            try {
                $content = ZgwResource::downloadByUrl($connectionName, $document->inhoud);
            } catch (\Throwable $e) {
                Log::error('CreateDocumentsZipJob: document ophalen mislukt', [
                    'zaak_id' => $zaak->id,
                    'uuid' => $uuid,
                    'error' => $e->getMessage(),
                ]);

                $unretrievable[] = $document->bestandsnaam ?: $document->titel;

                continue;
            }

            $fileName = $document->bestandsnaam ?: ($document->titel.'.bin');

            // Strip any directory segments so a crafted bestandsnaam (e.g. "../x")
            // cannot become a traversal path in the archive (zip slip on extract).
            $fileName = basename(str_replace('\\', '/', $fileName));

            if ($fileName === '' || $fileName === '.' || $fileName === '..') {
                $fileName = $uuid.'.bin';
            }

            $candidate = self::uniqueName($fileName, $usedNames);
            $usedNames[] = $candidate;

            $zip->addFromString($candidate, $content);
        }

        if ($unretrievable !== [] || $gone !== []) {
            $zip->addFromString(
                self::uniqueName((string) __('shared/actions.download_documents.missing.file_name'), $usedNames),
                self::missingDocumentsNotice($unretrievable, $gone),
            );
        }

        $zip->close();

        if (! file_exists($zipPath) || filesize($zipPath) === 0) {
            return null;
        }

        Cache::put("document_zip.{$token}", [
            'path' => "zips/{$token}.zip",
            'zaak_id' => $zaak->id,
            'user_id' => $userId,
        ], now()->addDay());

        Storage::disk('local')->setVisibility("zips/{$token}.zip", 'private');

        return $token;
    }

    /**
     * A file name that is not in use inside the archive yet, numbering it when it is.
     *
     * @param  array<int, string>  $usedNames
     */
    private static function uniqueName(string $fileName, array $usedNames): string
    {
        $base = pathinfo($fileName, PATHINFO_FILENAME);
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $suffix = 0;
        $candidate = $fileName;

        while (in_array($candidate, $usedNames, true)) {
            $suffix++;
            $candidate = $ext !== '' ? "{$base}_{$suffix}.{$ext}" : "{$base}_{$suffix}";
        }

        return $candidate;
    }

    /**
     * The text file that names the documents that could not be put in the archive.
     *
     * An archive that silently holds fewer files than were selected looks complete, so it
     * says which ones are missing instead. The two causes are listed apart, because what
     * the reader can do about them differs: a document that could not be fetched is still
     * on the zaak and trying again can help, one that is no longer on the zaak will not
     * come back. A cause without any documents is left out entirely.
     *
     * @param  array<int, string>  $unretrievable
     * @param  array<int, string>  $gone
     */
    private static function missingDocumentsNotice(array $unretrievable, array $gone): string
    {
        $notice = (string) __('shared/actions.download_documents.missing.intro')."\n";

        foreach (['unretrievable' => $unretrievable, 'gone' => $gone] as $cause => $names) {
            if ($names === []) {
                continue;
            }

            $notice .= "\n".(string) __("shared/actions.download_documents.missing.{$cause}.heading")."\n"
                .implode("\n", array_map(
                    // The names come from whoever uploaded the document, so line
                    // breaks are folded into spaces to keep one name per line.
                    static fn (string $name): string => '- '.str_replace(["\r", "\n"], ' ', $name),
                    $names,
                ))."\n\n"
                .(string) __("shared/actions.download_documents.missing.{$cause}.outro", ['app_name' => config('app.name')])."\n";
        }

        return $notice;
    }
}
