<?php

namespace App\Jobs;

use App\ValueObjects\OpenNotification;
use App\ValueObjects\OzZaak;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Woweb\Openzaak\ObjectsApi;
use Woweb\Openzaak\Openzaak;

class CheckAndSetZaakobject implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(private OpenNotification $notification) {}

    /**
     * Execute the job.
     */
    public function handle(Openzaak $openzaak, ObjectsApi $objectsapi): void
    {
        $zaakUrl = $this->notification->resourceUrl;

        $zaak = new OzZaak(...$openzaak->get($zaakUrl.'?expand=zaakobjecten')->toArray());

        if ($zaak->data_object_url) {
            // Zaakobject linking to the objects API already exists, the zaakobject notification already triggered the creation chain.
            return;
        }

        // Search for an object created in the last 3 minutes in the Objects API.
        $since = Carbon::now()->subMinutes(3)->format('Y-m-d');

        $objects = $objectsapi->getAll([
            'startAt__gte' => $since,
        ]);

        if ($objects->isEmpty()) {
            Log::warning('CheckAndSetZaakobject: geen recent object gevonden in de Objects API', [
                'zaakUrl' => $zaakUrl,
                'since' => $since,
            ]);

            return;
        }

        // Take the most recently registered object.
        $object = $objects->first();
        $objectUrl = $object->url ?? (rtrim(config('openzaak.objectsapi.url'), '/')
            .'/api/v2/objects/'
            .$object->uuid);

        $openzaak->zaken()->zaakobjecten()->store([
            'zaak' => $zaak->url,
            'object' => $objectUrl,
            'objectType' => 'overige',
            'objectTypeOverige' => 'formulier_inzending',
            'objectTypeOverigeDefinitie' => [
                'url' => env('OBJECTSAPI_OBJECTSTYPE_FORMULIER_URL'),
                'schema' => '.jsonSchema',
                'objectData' => '.record.data',
            ],
            'relatieomschrijving' => 'Formulier inzending gekoppeld aan de zaak',
            'objectIdentificatie' => [
                'overigeData' => $object->uuid,
            ]
        ]);

        // The zaakobject creation will trigger a notification which starts the normal zaak creation chain.
    }
}