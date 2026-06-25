<?php

declare(strict_types=1);

namespace App\Jobs\Zaak;

use App\Models\User;
use App\Models\Zaak;
use App\Notifications\DocumentsZipReady;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Woweb\Openzaak\Openzaak;
use ZipArchive;

final class CreateDocumentsZipJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, string>  $documentUuids
     */
    public function __construct(
        public readonly Zaak $zaak,
        public readonly array $documentUuids,
        public readonly int $userId,
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);

        $token = self::buildZip($this->zaak, $this->documentUuids, $this->userId);

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
     */
    public static function buildZip(Zaak $zaak, array $documentUuids, int $userId): ?string
    {
        $oz = new Openzaak;
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

        foreach ($documentUuids as $uuid) {
            $document = $zaak->documenten->where('uuid', $uuid)->first();

            if ($document === null) {
                Log::warning('CreateDocumentsZipJob: document niet gevonden', [
                    'zaak_id' => $zaak->id,
                    'uuid' => $uuid,
                ]);

                continue;
            }

            try {
                $content = $oz->getRaw($document->inhoud);
            } catch (\Throwable $e) {
                Log::error('CreateDocumentsZipJob: document ophalen mislukt', [
                    'zaak_id' => $zaak->id,
                    'uuid' => $uuid,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $fileName = $document->bestandsnaam ?: ($document->titel.'.bin');

            // Deduplicate filenames within the zip.
            $base = pathinfo($fileName, PATHINFO_FILENAME);
            $ext = pathinfo($fileName, PATHINFO_EXTENSION);
            $suffix = 0;
            $candidate = $fileName;
            while (in_array($candidate, $usedNames, true)) {
                $suffix++;
                $candidate = $ext !== '' ? "{$base}_{$suffix}.{$ext}" : "{$base}_{$suffix}";
            }
            $usedNames[] = $candidate;

            $zip->addFromString($candidate, $content);
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
}
