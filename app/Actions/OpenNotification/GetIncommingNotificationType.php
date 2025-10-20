<?php

namespace App\Actions\OpenNotification;

use App\Enums\OpenNotificationType;
use App\ValueObjects\OpenNotification;
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

            return is_string($objectUrl) && str_contains($objectUrl, config('openzaak.objectsapi.url')) ? OpenNotificationType::CreateZaak : null;
        } elseif (($data['actie'] === 'update' || $data['actie'] === 'partial_update') && $data['kanaal'] === 'zaken' && $data['resource'] === 'zaakeigenschap') {
            return OpenNotificationType::UpdateZaakEigenschap;
        } elseif ($data['actie'] === 'create' && $data['kanaal'] === 'zaken' && $data['resource'] === 'status') {
            // zaak status created, this happens when a status is changed on a zaak
            return OpenNotificationType::ZaakStatusChanged;
        } elseif ($data['actie'] === 'create' && $data['kanaal'] === 'documenten' && $data['resource'] === 'enkelvoudiginformatieobject') {
            return OpenNotificationType::NewZaakDocument;
        } elseif (($data['actie'] === 'update' || $data['actie'] === 'partial_update') && $data['kanaal'] === 'documenten' && $data['resource'] === 'enkelvoudiginformatieobject') {
            return OpenNotificationType::UpdatedZaakDocument;
        }

        // TODO: Implement other notification types

        return null;
    }
}
