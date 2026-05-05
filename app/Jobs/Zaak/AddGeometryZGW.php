<?php

declare(strict_types=1);

namespace App\Jobs\Zaak;

use App\EventForm\State\FormState;
use App\EventForm\Submit\EventLocationGeometryBuilder;
use App\EventForm\Submit\ZaakeigenschappenMap;
use App\Models\Zaak;
use App\ValueObjects\OzZaak;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Woweb\Openzaak\Openzaak;

/**
 * Schrijft de zaakgeometrie (line/polygons/adres-punten) op de ZGW-zaak
 * en registreert BAG-adressen als zaakobjecten type=`adres`.
 *
 * Input is nu de lokale `Zaak`; de event_location-array komt uit
 * `form_state_snapshot` via `ZaakeigenschappenMap`. Als er al een
 * geometrie op de ZGW-zaak staat, doen we niets.
 */
class AddGeometryZGW implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Zaak $zaak) {}

    public function handle(
        Openzaak $openzaak,
        ZaakeigenschappenMap $map,
        EventLocationGeometryBuilder $geometryBuilder,
    ): void {

        if(str_contains($this->zaak->zgw_zaak_url, 'https://zaken.preprod-rx-services.nl/')) {
            // rx mission gives a http 500 response when patch the zaak witch geometryCollection as zaakgeometrie, skip this for now
            return;
        }

        if (! $this->zaak->zgw_zaak_url) {
            return;
        }

        $ozZaak = new OzZaak(...$openzaak->get($this->zaak->zgw_zaak_url)->toArray());
        if ($ozZaak->zaakgeometrie) {
            return;
        }

        $state = FormState::fromSnapshot($this->zaak->form_state_snapshot ?? []);
        $eventLocation = $map->buildEventLocation($state);
        if ($eventLocation === []) {
            return;
        }

        $geoJson = $geometryBuilder->buildGeoJson($eventLocation);
        if ($geoJson) {
            $openzaak->zaken()->zaken()->patch($ozZaak->uuid, [
                'zaakgeometrie' => $geoJson,
            ]);
        }

        foreach ($geometryBuilder->collectedAddresses() as $bagObject) {
            $openzaak->zaken()->zaakobjecten()->store([
                'zaak' => $ozZaak->url,
                'objectType' => 'adres',
                'relatieomschrijving' => 'Adres van het evenement',
                'objectIdentificatie' => [
                    'wplWoonplaatsNaam' => $bagObject->woonplaatsnaam,
                    'gorOpenbareRuimteNaam' => $bagObject->id,
                    'huisnummer' => $bagObject->huisnummer,
                    'huisletter' => $bagObject->huisletter,
                    'huisnummertoevoeging' => $bagObject->huisnummertoevoeging,
                    'postcode' => $bagObject->postcode,
                ],
            ]);
        }
    }
}
