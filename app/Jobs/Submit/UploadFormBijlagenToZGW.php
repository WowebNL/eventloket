<?php

declare(strict_types=1);

namespace App\Jobs\Submit;

use App\Enums\DocumentVertrouwelijkheden;
use App\EventForm\Schema\EventFormSchema;
use App\Models\Zaak;
use App\ValueObjects\ZGW\Informatieobject;
use Filament\Forms\Components\FileUpload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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

        $bestandPaden = [];
        foreach ($this->collectFileUploadKeys() as $key) {
            $value = $values[$key] ?? null;
            if (is_string($value) && $value !== '') {
                $bestandPaden[] = $value;

                continue;
            }
            if (is_array($value)) {
                foreach ($value as $entry) {
                    if (is_string($entry) && $entry !== '') {
                        $bestandPaden[] = $entry;
                    }
                }
            }
        }

        if ($bestandPaden === []) {
            return;
        }

        // Filter eerst op aanwezigheid op disk; pas resolve & POST als er
        // daadwerkelijk een bestand te uploaden valt. Voorkomt onnodige
        // ZGW-roundtrips wanneer alle paden verdwenen zijn.
        $disk = Storage::disk(config('filament.default_filesystem_disk', 'local'));
        $aanwezig = [];
        foreach ($bestandPaden as $pad) {
            if (! $disk->exists($pad)) {
                Log::warning('UploadFormBijlagenToZGW: bijlage ontbreekt op disk', [
                    'zaak_id' => $this->zaak->id,
                    'pad' => $pad,
                ]);

                continue;
            }
            $aanwezig[] = $pad;
        }
        if ($aanwezig === []) {
            return;
        }

        $informatieobjecttype = $this->resolveInformatieobjecttype();
        $oz = new Openzaak;

        foreach ($aanwezig as $pad) {
            $content = (string) $disk->get($pad);
            $bestandsnaam = basename($pad);

            $info = new Informatieobject(...$oz->documenten()->enkelvoudiginformatieobjecten()->store([
                'bronorganisatie' => $this->zaak->openzaak->bronorganisatie,
                'creatiedatum' => now()->format('Y-m-d'),
                'vertrouwelijkheidaanduiding' => DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
                'titel' => $bestandsnaam,
                'auteur' => $this->zaak->organisation?->name ?? 'Organisator',
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

    private function resolveInformatieobjecttype(): string
    {
        $first = $this->zaak->zaaktype?->document_types?->first();
        if (! $first || ! property_exists($first, 'url') || $first->url === '') {
            throw new RuntimeException(
                'Geen informatieobjecttype gevonden voor zaaktype '
                .($this->zaak->zaaktype?->id ?? '?')
                .' — kan bijlagen niet uploaden.'
            );
        }

        return (string) $first->url;
    }
}
