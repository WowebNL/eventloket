<?php

declare(strict_types=1);

namespace App\Jobs\Submit;

use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Zaak;
use App\Services\Zgw\ZaaktypeBlueprint;
use App\Services\Zgw\ZgwConnectionConfig;
use App\ValueObjects\ZGW\Informatieobject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Woweb\Zgw\Facades\Zgw;

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
    use Queueable;

    public function __construct(public readonly Zaak $zaak) {}

    public function handle(): void
    {
        if (! $this->zaak->zgw_zaak_url) {
            Log::warning('UploadSubmissionPdfToZGW: zaak heeft geen ZGW-url', [
                'zaak_id' => $this->zaak->id,
            ]);

            return;
        }

        $path = sprintf('zaken/%s/aanvraagformulier.pdf', $this->zaak->id);
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

        $connectionName = $this->zaak->zgwConnectionName();
        $connection = Zgw::connection($connectionName);
        $info = new Informatieobject(...$connection->documenten()->enkelvoudiginformatieobjecten()->store([
            'bronorganisatie' => $this->zaak->openzaak->bronorganisatie,
            'creatiedatum' => now()->format('Y-m-d'),
            'vertrouwelijkheidaanduiding' => ZgwConnectionConfig::systemUploadDefault($connectionName),
            'titel' => 'Aanvraagformulier '.$this->zaak->reference_data->naam_evenement,
            'auteur' => 'Eventloket',
            'taal' => 'dut',
            'bestandsnaam' => 'aanvraagformulier.pdf',
            'bestandsomvang' => strlen($content),
            'formaat' => 'application/pdf',
            'inhoud' => base64_encode($content),
            'informatieobjecttype' => $informatieobjecttype,
            'indicatieGebruiksrecht' => false,
        ]));

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

        $this->zaak->clearZgwCache();
    }

    private function resolveInformatieobjecttype(): string
    {
        // The aanvraagformulier PDF keeps the historical "first type" fallback
        // (no "bijlage" omschrijving preference), but honours an explicit
        // blueprint bijlage-type when one is configured.
        $mapping = MunicipalityZaaktypeMapping::forZaaktype($this->zaak->zaaktype);
        $chosen = ZaaktypeBlueprint::bijlageInformatieobjecttype($mapping, $this->zaak->document_types, matchBijlageInOmschrijving: false);

        if (! $chosen || ! $chosen->url) {
            throw new RuntimeException(
                'Geen informatieobjecttype gevonden voor zaaktype '
                .($this->zaak->zaaktype->id ?? '?')
                .' — kan PDF niet uploaden.'
            );
        }

        return (string) $chosen->url;
    }
}
