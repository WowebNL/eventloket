<?php

namespace App\Actions\OpenNotification;

use App\Enums\OpenNotificationType;
use App\ValueObjects\OpenNotification;
use Illuminate\Support\Facades\Log;
use Woweb\Openzaak\Openzaak;

/**
 * Check incoming notifications and dispatch correct handle event
 */
class GetIncommingNotificationType
{
    public function handle(Openzaak $openzaak, OpenNotification $notification): ?OpenNotificationType
    {
        $data = $notification->toArray();
        // Last OpenForms action is to connect the element in the object api to the zaak.
        // The connection is made to create a "zaakobject" on a zaak
        if ($data['actie'] === 'create' && $data['kanaal'] === 'zaken' && $data['resource'] === 'zaakobject') {

            // check the zaakobject if it contains an url to the objects api
            // NOTE: no check on objecttype for now, check later if this is needed
            $objectUrl = $openzaak->get($data['resourceUrl'])->get('object');

            if (! is_string($objectUrl)) {
                Log::warning('OpenNotification: zaakobject has no object URL', ['resourceUrl' => $data['resourceUrl']]);

                return null;
            }

            if (! str_contains($objectUrl, config('openzaak.objectsapi.url'))) {
                Log::warning('OpenNotification: zaakobject object URL does not match OBJECTSAPI_URL — notification dropped', [
                    'objectUrl' => $objectUrl,
                    'objectsapiUrl' => config('openzaak.objectsapi.url'),
                ]);

                return null;
            }

            return OpenNotificationType::CreateZaak;
        } elseif (($data['actie'] === 'update' || $data['actie'] === 'partial_update') && $data['kanaal'] === 'zaken' && $data['resource'] === 'zaakeigenschap') {
            return OpenNotificationType::UpdateZaakEigenschap;
        } elseif ($data['actie'] === 'create' && $data['kanaal'] === 'zaken' && $data['resource'] === 'status') {
            // zaak status created, this happens when a status is changed on a zaak
            return OpenNotificationType::ZaakStatusChanged;
        } elseif ($data['actie'] === 'create' && $data['kanaal'] === 'documenten' && $data['resource'] === 'enkelvoudiginformatieobject') {
            return OpenNotificationType::NewZaakDocument;
        } elseif (($data['actie'] === 'update' || $data['actie'] === 'partial_update') && $data['kanaal'] === 'documenten' && $data['resource'] === 'enkelvoudiginformatieobject') {
            return OpenNotificationType::UpdatedZaakDocument;
        } elseif ($data['actie'] === 'create' && $data['kanaal'] === 'zaken' && $data['resource'] === 'zaak') {
            // temporary because OF does not create the zaakobject for OneGround
            return OpenNotificationType::checkAndSetZaakobject;
        }

        // TODO: Implement other notification types
        Log::debug('OpenNotification: unhandled notification type — dropped', [
            'actie' => $data['actie'],
            'kanaal' => $data['kanaal'],
            'resource' => $data['resource'],
        ]);

        return null;
    }
}
