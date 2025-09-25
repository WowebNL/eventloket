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

        if ($geoJson) {
            $openzaak->zaken()->zaken()->patch($zaak->uuid, [
                'zaakgeometrie' => $geoJson,
            ]);
        }

    }
}
