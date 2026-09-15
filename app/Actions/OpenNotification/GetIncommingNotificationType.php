<?php

namespace App\Actions\OpenNotification;

use App\Enums\OpenNotificationType;
use App\ValueObjects\OpenNotification;
use Illuminate\Support\Facades\Log;

/**
 * Check incoming notifications and dispatch correct handle event.
 *
 * The `CreateZaak` branch has been removed: in the Filament flow we create the
 * zaak ourselves at submit, so the OpenZaak webhook that triggered Open Forms
 * submissions is no longer relevant. Status/besluit/document notifications
 * keep working because they can still arrive for our own zaken.
 */
class GetIncommingNotificationType
{
    public function handle(OpenNotification $notification): ?OpenNotificationType
    {
        $data = $notification->toArray();

        // `create` is included: an eigenschap added to the zaak in the
        // zaaksysteem carries a value we display, so it has to refresh the
        // reference data just like a changed one.
        if (in_array($data['actie'], ['create', 'update', 'partial_update'], true) && $data['kanaal'] === 'zaken' && $data['resource'] === 'zaakeigenschap') {
            return OpenNotificationType::UpdateZaakEigenschap;
        } elseif ($data['actie'] === 'create' && $data['kanaal'] === 'zaken' && $data['resource'] === 'status') {
            // zaak status created, this happens when a status is changed on a zaak
            return OpenNotificationType::ZaakStatusChanged;
        } elseif ($data['actie'] === 'create' && $data['kanaal'] === 'documenten' && $data['resource'] === 'enkelvoudiginformatieobject') {
            return OpenNotificationType::NewZaakDocument;
        } elseif (($data['actie'] === 'update' || $data['actie'] === 'partial_update') && $data['kanaal'] === 'documenten' && $data['resource'] === 'enkelvoudiginformatieobject') {
            return OpenNotificationType::UpdatedZaakDocument;
        } elseif ($data['kanaal'] === 'besluiten') {
            // Every resource on this channel (besluit and besluitinformatieobject)
            // carries the besluit url as hoofdObject. We subscribe to the channel,
            // so without this branch a besluit taken in the ZGW backend itself was
            // dropped and the zaak kept serving its cached besluiten.
            return OpenNotificationType::BesluitChanged;
        } elseif ($data['kanaal'] === 'zaaktypen') {
            // Every resource on this channel (zaaktype and its child resources
            // like statustype or resultaattype) carries the zaaktype version url
            // as hoofdObject, so one case covers publish, change and destroy.
            return OpenNotificationType::ZaaktypeChanged;
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
