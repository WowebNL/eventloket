<?php

declare(strict_types=1);

namespace App\Jobs\Submit;

use App\Enums\DocumentVertrouwelijkheden;
use App\Models\Zaak;
use App\ValueObjects\ZGW\Informatieobject;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Woweb\Openzaak\Openzaak;

/**
 * Upload het zojuist gegenereerde inzendingsbewijs (PDF) als
 * zaakinformatieobject naar OpenZaak. Behandelaars zien 'm dan onder
 * de "Bestanden"-tab van de zaak — net als documenten die ze zelf
 * uploaden via UploadDocumentAction.
 *
 * Wordt aan het einde van GenerateSubmissionPdf::handle() gedispatched
 * — als de PDF-write faalt slaan we deze stap automatisch over.
 */
final class UploadSubmissionPdfToZGW implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Zaak $zaak) {}

    public function handle(): void
    {
        if (! $this->zaak->zgw_zaak_url) {
            Log::warning('UploadSubmissionPdfToZGW: zaak heeft geen ZGW-url', [
                'zaak_id' => $this->zaak->id,
            ]);

            return;
        }

        $path = sprintf('zaken/%s/submission-report.pdf', $this->zaak->id);
        if (! Storage::disk('local')->exists($path)) {
            // Geen PDF om te uploaden — een eerdere job heeft 'm niet
            // weggeschreven. Loggen en stilletjes terug; we willen geen
            // retry-storm op een ontbrekend bestand.
            Log::warning('UploadSubmissionPdfToZGW: PDF ontbreekt', [
                'zaak_id' => $this->zaak->id,
                'path' => $path,
            ]);

            return;
        }

        $content = (string) Storage::disk('local')->get($path);
        $informatieobjecttype = $this->resolveInformatieobjecttype();

        $oz = new Openzaak;
        $info = new Informatieobject(...$oz->documenten()->enkelvoudiginformatieobjecten()->store([
            'bronorganisatie' => $this->zaak->openzaak->bronorganisatie,
            'creatiedatum' => now()->format('Y-m-d'),
            'vertrouwelijkheidaanduiding' => DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
            'titel' => 'Inzendingsbewijs '.$this->zaak->public_id,
            'auteur' => 'Eventloket',
            'taal' => 'dut',
            'bestandsnaam' => 'inzendingsbewijs.pdf',
            'bestandsomvang' => strlen($content),
            'formaat' => 'application/pdf',
            'inhoud' => base64_encode($content),
            'informatieobjecttype' => $informatieobjecttype,
            'indicatieGebruiksrecht' => false,
        ]));

        $oz->zaken()->zaakinformatieobjecten()->store([
            'zaak' => $this->zaak->zgw_zaak_url,
            'informatieobject' => $info->url,
        ]);
    }

    private function resolveInformatieobjecttype(): string
    {
        $first = $this->zaak->zaaktype?->document_types?->first();
        if (! $first || ! property_exists($first, 'url') || $first->url === '') {
            throw new RuntimeException(
                'Geen informatieobjecttype gevonden voor zaaktype '
                .($this->zaak->zaaktype?->id ?? '?')
                .' — kan PDF niet uploaden.'
            );
        }

        return (string) $first->url;
    }
}
