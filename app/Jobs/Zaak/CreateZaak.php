<?php

namespace App\Jobs\Zaak;

use App\Models\Organisation;
use App\Models\Users\OrganiserUser;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use App\ValueObjects\ObjectsApi\FormSubmissionObject;
use App\ValueObjects\OzZaak;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Woweb\Openzaak\ObjectsApi;
use Woweb\Openzaak\Openzaak;

class CreateZaak implements ShouldQueue
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
        $ozZaak = new OzZaak(...$openzaak->get($this->zaakUrlZGW.'?expand=zaakobjecten,eigenschappen,status,status.statustype,rollen')->toArray());
        $formSubmissionObject = new FormSubmissionObject(...$objectsapi->get(basename($ozZaak->data_object_url))->toArray());
        $zaaktype = Zaaktype::where(['zgw_zaaktype_url' => $ozZaak->zaaktype, 'is_active' => true])->first();

        if (! $zaaktype) {
            // zaaktype not found in local database or not active
            Log::warning('Zaaktype not found or inactive', ['zaaktype' => $ozZaak->zaaktype]);

            return;
        }
        $organisation = Organisation::where('uuid', $formSubmissionObject->organisation_uuid)->first();
        $user = OrganiserUser::where('uuid', $formSubmissionObject->user_uuid)->first();

        if ($ozZaak->initiator && $ozZaak->initiator->betrokkeneType === 'natuurlijk_persoon') {
            $organisator = $ozZaak->initiator->betrokkeneIdentificatie['voornamen'].' '.$ozZaak->initiator->betrokkeneIdentificatie['geslachtsnaam'];
        } elseif ($ozZaak->initiator && $ozZaak->initiator->betrokkeneType === 'niet_natuurlijk_persoon') {
            $organisator = $ozZaak->initiator->betrokkeneIdentificatie['statutaireNaam'].' - '.$ozZaak->initiator->contactpersoonRol['naam'];
        } else {
            $organisator = '';
        }

        Zaak::updateOrCreate(
            ['zgw_zaak_url' => $ozZaak->url],
            [
                'public_id' => $ozZaak->identificatie,
                'zaaktype_id' => $zaaktype->id,
                'data_object_url' => $ozZaak->data_object_url,
                'organisation_id' => $organisation?->id,
                'organiser_user_id' => $user?->id,
                'reference_data' => new ZaakReferenceData(
                    ...array_merge(
                        $ozZaak->eigenschappen_key_value,
                        [
                            'registratiedatum' => $ozZaak->registratiedatum,
                            'status_name' => $ozZaak->status_name,
                            'statustype_url' => $ozZaak->statustype_url,
                            'organisator' => $organisator,
                        ]
                    )
                ),
            ]
        );
    }
}
