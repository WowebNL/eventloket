<?php

declare(strict_types=1);

namespace App\Jobs\Zaak;

use App\EventForm\State\FormState;
use App\EventForm\Submit\EventLocationGeometryBuilder;
use App\EventForm\Submit\ZaakeigenschappenMap;
use App\Exceptions\LocatieserverUnavailableException;
use App\Models\Zaak;
use App\ValueObjects\OzZaak;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Woweb\Openzaak\Openzaak;

/**
 * Writes the zaakgeometrie (line/polygons/address points) onto the ZGW zaak
 * and registers BAG addresses as zaakobjecten of type `adres`.
 *
 * Input is the local `Zaak`; the event_location array comes from
 * `form_state_snapshot` through `ZaakeigenschappenMap`. A zaak that already
 * carries a geometry is left alone, so whatever is written here is what the
 * zaak keeps. That makes an incomplete write permanent, which is why an
 * address lookup that could not be completed aborts the job instead.
 */
class AddGeometryZGW implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Zaak $zaak) {}

    public function handle(
        Openzaak $openzaak,
        ZaakeigenschappenMap $map,
        EventLocationGeometryBuilder $geometryBuilder,
    ): void {
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

        // Nobody is waiting on this job, so the address lookups get the longer
        // budget rather than the short one that keeps a page responsive.
        $geometryBuilder = $geometryBuilder->forBackgroundWork();

        $geoJson = $geometryBuilder->buildGeoJson($eventLocation);

        // An address that could not be looked up because the location service
        // was unreachable is not an address that does not exist. Writing now
        // would store a geometry missing that point, and the guard above then
        // keeps it that way forever. Fail instead, so the queue runs the whole
        // job again once the service is back.
        if ($geometryBuilder->hadUnreachableLookups()) {
            throw new LocatieserverUnavailableException(
                'Skipped writing the zaakgeometrie: an address lookup could not be completed.'
            );
        }

        if ($geoJson) {
            $openzaak->zaken()->zaken()->patch($ozZaak->uuid, [
                'zaakgeometrie' => json_decode($geoJson, true),
            ]);
            $this->zaak->clearZgwCache();
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
