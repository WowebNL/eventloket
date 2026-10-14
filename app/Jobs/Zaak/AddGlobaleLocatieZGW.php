<?php

declare(strict_types=1);

namespace App\Jobs\Zaak;

use App\EventForm\State\FormState;
use App\EventForm\Submit\EventAddressFormatter;
use App\Models\Zaak;
use App\Services\Zgw\ZaakReadModel;
use App\Services\Zgw\ZgwConnectionConfig;
use App\Services\Zgw\ZgwResource;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Woweb\Zgw\Facades\Zgw;

/**
 * Registers the event's location as a zaakobject of type "overige" with
 * objectTypeOverige "GlobaleLocatie".
 *
 * The value is the BAG address of the event when the aanvraag has one, because
 * an address tells a behandelaar more than a self-chosen location name; every
 * address of the aanvraag is included, comma separated. Only when there is no
 * BAG address (an outdoor event or a route) does it fall back to the composed
 * location names on the zaak's reference data (locaties_evenement).
 *
 * This applies to every connection, including our own OpenZaak (main).
 */
class AddGlobaleLocatieZGW implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Zaak $zaak) {}

    public function handle(): void
    {
        if (! $this->zaak->zgw_zaak_url) {
            return;
        }

        $locaties = $this->globaleLocatie();
        if ($locaties === null) {
            return;
        }

        $connectionName = $this->zaak->zgwConnectionName();
        $connection = Zgw::connection($connectionName);

        $ozZaak = ZaakReadModel::fromArray(ZgwResource::byUrl($connectionName, $this->zaak->zgw_zaak_url));

        $connection->zaken()->zaakobjecten()->store([
            'zaak' => $ozZaak->url,
            'objectType' => 'overige',
            'objectTypeOverige' => 'GlobaleLocatie',
            'relatieomschrijving' => 'Globale locatie van het evenement',
            // For objectType "overige" the identification lives under
            // objectIdentificatie.overigeData, which is required. The ZGW
            // standard types it as a free-form object, so OpenZaak wants
            // `{naam: ...}`; OneGround (RX Mission) deviates and stores/expects
            // overigeData as a bare string, so send just the names there.
            'objectIdentificatie' => [
                'overigeData' => ZgwConnectionConfig::isOneGround($connectionName)
                    ? $locaties
                    : ['naam' => $locaties],
            ],
        ]);
    }

    /**
     * The BAG address(es) from the submitted form, with the composed location
     * names as fallback. Null when the aanvraag carries neither.
     */
    private function globaleLocatie(): ?string
    {
        $state = FormState::fromSnapshot($this->zaak->form_state_snapshot ?? []);
        $adressen = EventAddressFormatter::fromState($state);
        if ($adressen !== null) {
            return $adressen;
        }

        $namen = $this->zaak->reference_data->locaties_evenement;

        return is_string($namen) && $namen !== '' ? $namen : null;
    }
}
