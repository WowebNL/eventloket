<?php

namespace App\Actions\OpenNotification;

use App\Enums\OpenNotificationType;
use App\ValueObjects\OpenNotification;
use Illuminate\Support\Facades\Log;
use Woweb\Openzaak\Openzaak;

/**
 * Check incoming notifications and dispatch correct handle event.
 *
 * De `CreateZaak`-tak is verwijderd — in de nieuwe Filament-flow maken
 * wij de zaak zelf aan bij submit, dus de OpenZaak-webhook die
 * OF-submissions triggerde is niet meer relevant. Status/besluit/
 * document-notificaties blijven wel werken omdat die ook bij onze
 * eigen zaken nog kunnen binnenkomen.
 */
class GetIncommingNotificationType
{
    public function handle(Openzaak $openzaak, OpenNotification $notification): ?OpenNotificationType
    {
        $data = $notification->toArray();

        if (($data['actie'] === 'update' || $data['actie'] === 'partial_update') && $data['kanaal'] === 'zaken' && $data['resource'] === 'zaakeigenschap') {
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
        Log::debug('OpenNotification: unhandled notification type — dropped', [
            'actie' => $data['actie'],
            'kanaal' => $data['kanaal'],
            'resource' => $data['resource'],
        ]);

        return null;
    }
}
