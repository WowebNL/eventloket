<?php

declare(strict_types=1);

namespace App\Jobs\Zaak;

use App\EventForm\State\FormState;
use App\EventForm\Submit\ZaakeigenschappenMap;
use App\Models\Zaak;
use App\Normalizers\OpenFormsNormalizer;
use App\ValueObjects\OzZaak;
use App\ValueObjects\ZGW\CatalogiEigenschap;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Woweb\Openzaak\Openzaak;

/**
 * Schrijft zaakeigenschappen op de ZGW-zaak op basis van de FormState
 * in `$zaak->form_state_snapshot`.
 *
 * Vervangt de oude implementatie die uit Objects API las. De
 * mapping-lijst staat in `ZaakeigenschappenMap`, OF's oude gedrag
 * wordt 1-op-1 gevolgd: eigenschap niet in catalogus → stil overslaan;
 * lege waarde → stil overslaan; al aanwezig op de zaak → overslaan.
 */
class AddZaakeigenschappenZGW implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Zaak $zaak) {}

    public function handle(Openzaak $openzaak, ZaakeigenschappenMap $map): void
    {
        if (! $this->zaak->zgw_zaak_url) {
            return;
        }

        $state = FormState::fromSnapshot($this->zaak->form_state_snapshot ?? []);
        $ozZaak = new OzZaak(...$openzaak->get($this->zaak->zgw_zaak_url.'?expand=eigenschappen')->toArray());
        $catalogiEigenschappen = $openzaak->catalogi()->eigenschappen()
            ->getAll(['zaaktype' => $ozZaak->zaaktype])
            ->map(fn ($eigenschap) => new CatalogiEigenschap(...$eigenschap));

        $eigenschappen = $map->buildEigenschappen($state);

        // formsubmission_id: in OF een submission-kenmerk, bij ons het
        // lokale public_id (= OpenZaak identificatie).
        if ($this->zaak->public_id) {
            $eigenschappen[] = ['formsubmission_id' => $this->zaak->public_id];
        }

        foreach ($eigenschappen as $eigenschap) {
            $naam = (string) key($eigenschap);
            $waarde = current($eigenschap);

            if (Arr::first($ozZaak->eigenschappen, fn ($e) => $e->naam === $naam)) {
                continue;
            }

            $catalogiEigenschap = $catalogiEigenschappen->firstWhere('naam', $naam);
            if (! $catalogiEigenschap) {
                continue;
            }

            if (is_string($waarde) && (str_starts_with($waarde, '[') || str_starts_with($waarde, '{'))) {
                $waarde = OpenFormsNormalizer::normalizeJson($waarde);
            } elseif (str_contains($this->zaak->zgw_zaak_url, 'https://zaken.preprod-rx-services.nl/') && preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}/', $waarde)) {
                    $waarde = \Carbon\Carbon::parse($waarde)->format('YmdHis');
            }

            if ($waarde === null || $waarde === '' || $waarde === []) {
                continue;
            }

            $openzaak->zaken()->zaken()->zaakeigenschappen(basename($this->zaak->zgw_zaak_url))->store([
                'zaak' => $ozZaak->url,
                'eigenschap' => $catalogiEigenschap->url,
                'waarde' => is_scalar($waarde) ? (string) $waarde : (string) json_encode($waarde),
            ]);
        }
    }
}
