<?php

declare(strict_types=1);

namespace App\Jobs\Submit;

use App\Enums\DocumentVertrouwelijkheden;
use App\EventForm\Schema\EventFormSchema;
use App\Models\Zaak;
use App\Support\Uploads\DocumentUploadType;
use App\ValueObjects\ZGW\Informatieobject;
use Filament\Forms\Components\FileUpload;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ReflectionObject;
use RuntimeException;
use Woweb\Openzaak\Openzaak;

/**
 * Upload alle FileUpload-bijlagen die de organisator op de bijlagen-
 * stap heeft ge-upload als zaakinformatieobjecten naar OpenZaak. Per
 * bestand: één POST op enkelvoudiginformatieobjecten + één POST op
 * zaakinformatieobjecten (zelfde patroon als UploadDocumentAction).
 *
 * We walken EventFormSchema om de FileUpload-veld-keys te vinden i.p.v.
 * ze hard te coderen — wanneer er een FileUpload bijkomt valt 'ie zo
 * automatisch onder deze job.
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
        foreach ($this->collectFileUploadKeys() as $key) {
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
            if (! str_starts_with($pad, $toegestanePrefix)) {
                Log::warning('UploadFormBijlagenToZGW: bijlage-pad buiten de eigen upload-map geweigerd', [
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
            if (! DocumentUploadType::storedMimeTypeIsAllowed($mime)) {
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
        $oz = new Openzaak;

        foreach ($aanwezig as $pad => $bestandsnaam) {
            $content = (string) $disk->get($pad);

            $info = new Informatieobject(...$oz->documenten()->enkelvoudiginformatieobjecten()->store([
                'bronorganisatie' => $this->zaak->openzaak->bronorganisatie,
                'creatiedatum' => now()->format('Y-m-d'),
                'vertrouwelijkheidaanduiding' => DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
                'titel' => $bestandsnaam,
                'auteur' => $this->zaak->organiserUser->name ?? 'Organisator',
                'taal' => 'dut',
                'bestandsnaam' => $bestandsnaam,
                'bestandsomvang' => strlen($content),
                'formaat' => $disk->mimeType($pad) ?: 'application/octet-stream',
                'inhoud' => base64_encode($content),
                'informatieobjecttype' => $informatieobjecttype,
                'indicatieGebruiksrecht' => false,
            ]));

            $oz->zaken()->zaakinformatieobjecten()->store([
                'zaak' => $this->zaak->zgw_zaak_url,
                'informatieobject' => $info->url,
            ]);
        }

        $this->zaak->clearZgwCache();
    }

    /**
     * Walk EventFormSchema en verzamel alle veld-keys van FileUpload-
     * componenten. Reflectie nodig omdat we geen Livewire-container
     * hebben in een queue-job.
     *
     * @return list<string>
     */
    private function collectFileUploadKeys(): array
    {
        $keys = [];
        $walk = function (object $component) use (&$walk, &$keys): void {
            if ($component instanceof FileUpload) {
                $name = $component->getName();
                if ($name !== '') {
                    $keys[] = $name;
                }
            }
            if (! property_exists($component, 'childComponents')) {
                return;
            }
            $reflection = new ReflectionObject($component);
            $prop = $reflection->getProperty('childComponents');
            $prop->setAccessible(true);
            $children = $prop->getValue($component);
            if (! is_array($children)) {
                return;
            }
            foreach ($children as $list) {
                if (! is_array($list)) {
                    continue;
                }
                foreach ($list as $child) {
                    if (is_object($child)) {
                        $walk($child);
                    }
                }
            }
        };

        foreach (EventFormSchema::steps() as $step) {
            $walk($step);
        }

        return array_values(array_unique($keys));
    }

    /**
     * Voor nu: zoek het informatieobjecttype waarvan de omschrijving
     * "bijlage" bevat (case-insensitive); valt terug op het eerste type
     * als er geen treffer is. TODO: per upload-veld instelbaar maken,
     * waarschijnlijk via een mapping op Zaaktype (per gemeente kan het
     * informatieobjecttype namelijk verschillen per upload-veld).
     */
    private function resolveInformatieobjecttype(): string
    {
        $types = $this->zaak->zaaktype?->document_types;
        if (! $types || $types->isEmpty()) {
            throw new RuntimeException(
                'Geen informatieobjecttype gevonden voor zaaktype '
                .($this->zaak->zaaktype->id ?? '?')
                .' — kan bijlagen niet uploaden.'
            );
        }

        $bijlageType = $types->first(static fn ($type): bool => property_exists($type, 'omschrijving')
            && str_contains(mb_strtolower($type->omschrijving), 'bijlage')
        );
        $chosen = $bijlageType ?? $types->first();

        if (! $chosen || ! property_exists($chosen, 'url') || $chosen->url === '') {
            throw new RuntimeException(
                'Geen informatieobjecttype gevonden voor zaaktype '
                .($this->zaak->zaaktype->id ?? '?')
                .' — kan bijlagen niet uploaden.'
            );
        }

        return (string) $chosen->url;
    }
}
