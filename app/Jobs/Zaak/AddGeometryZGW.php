<?php

namespace App\Jobs\Zaak;

use App\ValueObjects\ObjectsApi\FormSubmissionObject;
use App\ValueObjects\OzZaak;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Woweb\Openzaak\ObjectsApi;
use Woweb\Openzaak\Openzaak;

class AddGeometryZGW implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(private string $zaakUrlZGW) {}

    /**
     * Execute the job.
     */
    public function handle(Openzaak $openzaak, ObjectsApi $objectsapi): void
    {
        $zaak = new OzZaak(...$openzaak->get($this->zaakUrlZGW.'?expand=zaakobjecten,eigenschappen')->toArray());
        if ($zaak->zaakgeometrie) {
            return; // geometry already exists
        }

        $formSubmissionObject = new FormSubmissionObject(...$objectsapi->get(basename($zaak->data_object_url))->toArray());
        $geoJson = $formSubmissionObject->getFormattedEventLocation();
        $zaakEventAddresses = $formSubmissionObject->zaakEventAddresses; // note this needs refactoring, variable zaakEventAddresses is filled in previous called method getFormattedEventLocation()

        if ($geoJson) {
            $openzaak->zaken()->zaken()->patch($zaak->uuid, [
                'zaakgeometrie' => $geoJson,
            ]);
        }

        // save the adresses as zaakobjecten
        if ($zaakEventAddresses) {
            foreach ($zaakEventAddresses as $bagObject) {
                $data = [
                    'zaak' => $zaak->url,
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
                ];

                $openzaak->zaken()->zaakobjecten()->store($data);
            }
        }

    }
}
