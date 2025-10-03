<?php

namespace App\Jobs\Zaak;

use App\ValueObjects\ObjectsApi\FormSubmissionObject;
use App\ValueObjects\OzZaak;
use App\ValueObjects\ZGW\CatalogiEigenschap;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Woweb\Openzaak\ObjectsApi;
use Woweb\Openzaak\Openzaak;

class AddZaakeigenschappenZGW implements ShouldQueue
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
        $formSubmissionObject = new FormSubmissionObject(...$objectsapi->get(basename($zaak->data_object_url))->toArray());
        $catalogiEigenschappen = $openzaak->catalogi()->eigenschappen()->getAll(['zaaktype' => $zaak->zaaktype])->map(fn ($eigenschap) => new CatalogiEigenschap(...$eigenschap));

        foreach ($formSubmissionObject->zaakeigenschappen as $eigenschap) {
            $eigenschapName = key($eigenschap);

            if (Arr::first($zaak->eigenschappen, fn ($value) => $value->naam == $eigenschapName)) {
                // eigenschap allready exists
                continue;
            }

            $catalogiEigenschap = $catalogiEigenschappen->firstWhere('naam', $eigenschapName);

            if (! $catalogiEigenschap) {
                // eigenschap not found in catalogi related to zaaktype
                continue;
            }

            $waarde = current($eigenschap);

            if ($waarde) {
                $data = [
                    'zaak' => $zaak->url,
                    'eigenschap' => $catalogiEigenschap->url,
                    'waarde' => current($eigenschap),
                ];

                $openzaak->zaken()->zaken()->zaakeigenschappen(basename($this->zaakUrlZGW))->store($data);
            }
        }

    }
}
