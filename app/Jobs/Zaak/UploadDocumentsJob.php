<?php

declare(strict_types=1);

namespace App\Jobs\Zaak;

use App\Models\User;
use App\Models\Zaak;
use App\Services\Zgw\ZgwResource;
use App\Support\Uploads\DocumentUploadType;
use App\ValueObjects\ZGW\Informatieobject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Woweb\Zgw\Facades\Zgw;

final class UploadDocumentsJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array{path: string, titel: string, original_name: string, informatieobjecttype: string}>  $files
     */
    public function __construct(
        public readonly Zaak $zaak,
        public readonly array $files,
        public readonly string $vertrouwelijkheidaanduiding,
        public readonly int $userId,
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        $connection = Zgw::connection($this->zaak->zgwConnectionName());
        $count = count($this->files);
        $uploaded = [];

        foreach ($this->files as $file) {
            $path = (string) ($file['path'] ?? '');
            if ($path === '' || ! Storage::exists($path)) {
                Log::warning('UploadDocumentsJob: bestand ontbreekt op disk', [
                    'zaak_id' => $this->zaak->id,
                    'path' => $path,
                ]);

                continue;
            }

            $formaat = DocumentUploadType::determineFormaat($path, $file['original_name'] ?? null);
            $bestandsnaam = DocumentUploadType::ensureFileNameHasExtension($file['original_name'] ?? '', $formaat);
            $titel = ($file['titel'] ?? '') !== '' ? $file['titel'] : pathinfo($bestandsnaam, PATHINFO_FILENAME);

            $informatieobject = new Informatieobject(...ZgwResource::ensureUuid($connection->documenten()->enkelvoudiginformatieobjecten()->store([
                'bronorganisatie' => $this->zaak->openzaak->bronorganisatie,
                'creatiedatum' => now()->format('Y-m-d'),
                'vertrouwelijkheidaanduiding' => $this->vertrouwelijkheidaanduiding,
                'titel' => $titel,
                'auteur' => $user !== null ? $user->name : 'Onbekend',
                'taal' => 'dut',
                'bestandsnaam' => $bestandsnaam,
                'bestandsomvang' => Storage::size($path),
                'formaat' => $formaat,
                'inhoud' => base64_encode((string) Storage::get($path)),
                'informatieobjecttype' => $file['informatieobjecttype'],
                'indicatieGebruiksrecht' => false,
            ])));

            $connection->zaken()->zaakinformatieobjecten()->store([
                'zaak' => $this->zaak->openzaak->url,
                'informatieobject' => $informatieobject->url,
            ]);

            Storage::delete($path);

            activity('document')
                ->event('created')
                ->causedBy($user)
                ->performedOn($this->zaak)
                ->withProperties([
                    'document_uuid' => $informatieobject->uuid,
                    'filename' => $informatieobject->bestandsnaam,
                    'titel' => $informatieobject->titel,
                    'vertrouwelijkheidaanduiding' => $informatieobject->vertrouwelijkheidaanduiding,
                ])
                ->log(__('activity/event.created'));

            $uploaded[] = $informatieobject;
        }

        Cache::forget("zaak.{$this->zaak->id}.documenten");

        if ($count > 1) {
            activity('document')
                ->event('multi_upload')
                ->causedBy($user)
                ->performedOn($this->zaak)
                ->withProperties(['count' => $count, 'uploaded' => count($uploaded)])
                ->log(__('activity/event.multi_upload', ['count' => $count]));
        }
    }
}
