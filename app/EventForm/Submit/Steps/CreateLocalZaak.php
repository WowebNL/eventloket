<?php

declare(strict_types=1);

namespace App\EventForm\Submit\Steps;

use App\EventForm\State\FormState;
use App\EventForm\Submit\MapFormStateToReferenceData;
use App\Models\Organisation;
use App\Models\Users\OrganiserUser;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\OzZaak;

/**
 * Synchrone stap: schrijft de lokale `zaken`-row op basis van de net
 * aangemaakte ZGW-zaak + de FormState. Dit is wat de oude `CreateZaak`
 * job deed nadat OF een zaak had aangemaakt en de webhook had gevuurd.
 * Nu doen we het zelf, zonder Objects API.
 *
 * De `form_state_snapshot`-kolom bevat de complete FormState zodat de
 * 6 vervolg-jobs (eigenschappen, geometry, doorkomsten etc.) daar hun
 * input uit kunnen lezen i.p.v. uit een Objects-API-record.
 */
final class CreateLocalZaak
{
    public function __construct(private readonly MapFormStateToReferenceData $mapReferenceData) {}

    public function execute(
        FormState $state,
        OzZaak $ozZaak,
        Zaaktype $zaaktype,
        OrganiserUser $user,
        Organisation $organisation,
    ): Zaak {
        $referenceData = $this->mapReferenceData->build(
            state: $state,
            statusName: 'Ingediend',
            statustypeUrl: '',
        );

        return Zaak::create([
            'public_id' => $ozZaak->identificatie,
            'zgw_zaak_url' => $ozZaak->url,
            'zaaktype_id' => $zaaktype->id,
            'data_object_url' => null, // Objects API is weg in nieuwe flow
            'organisation_id' => $organisation->id,
            'organiser_user_id' => $user->id,
            'reference_data' => $referenceData,
            'form_state_snapshot' => $state->toSnapshot(),
        ]);
    }
}
