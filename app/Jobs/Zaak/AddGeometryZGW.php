<?php

declare(strict_types=1);

namespace App\Jobs\Zaak;

use App\EventForm\State\FormState;
use App\EventForm\Submit\EventLocationGeometryBuilder;
use App\EventForm\Submit\ZaakeigenschappenMap;
use App\Models\Zaak;
use App\Services\Zgw\ZgwResource;
use App\ValueObjects\OzZaak;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Woweb\Zgw\Facades\Zgw;

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
    use Queueable;

    public function __construct(public readonly Zaak $zaak) {}

    public function handle(
        ZaakeigenschappenMap $map,
        EventLocationGeometryBuilder $geometryBuilder,
    ): void {
        if (! $this->zaak->zgw_zaak_url) {
            return;
        }

        $connectionName = $this->zaak->zgwConnectionName();
        $connection = Zgw::connection($connectionName);

        $ozZaak = new OzZaak(...ZgwResource::byUrl($connectionName, $this->zaak->zgw_zaak_url));
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
            $connection->zaken()->zaken()->patch($ozZaak->uuid, [
                'zaakgeometrie' => json_decode($geoJson, true),
            ]);
            $this->zaak->clearZgwCache();
        }

        foreach ($geometryBuilder->collectedAddresses() as $bagObject) {
            $connection->zaken()->zaakobjecten()->store([
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
