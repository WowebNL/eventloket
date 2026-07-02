<?php

declare(strict_types=1);

namespace App\EventForm\Submit\Steps;

use App\EventForm\State\FormState;
use App\EventForm\Submit\MapFormStateToReferenceData;
use App\Models\Organisation;
use App\Models\Users\OrganiserUser;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Services\Zgw\ZaakReadModel;
use Illuminate\Support\Facades\Log;
use Woweb\Zgw\Facades\Zgw;

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
    public function __construct(
        private readonly MapFormStateToReferenceData $mapReferenceData,
    ) {}

    public function execute(
        FormState $state,
        ZaakReadModel $ozZaak,
        Zaaktype $zaaktype,
        OrganiserUser $user,
        Organisation $organisation,
    ): Zaak {
        $referenceData = $this->mapReferenceData->build(
            state: $state,
            statusName: 'Ingediend',
            statustypeUrl: $this->resolveInitialStatustypeUrl($zaaktype, $ozZaak->zaaktype),
        );

        return Zaak::create([
            'public_id' => $ozZaak->identificatie,
            'zgw_zaak_url' => $ozZaak->url,
            'zaaktype_id' => $zaaktype->id,
            'zgw_zaaktype_url' => $ozZaak->zaaktype, // snapshot of the exact version used
            'data_object_url' => null, // Objects API is weg in nieuwe flow
            'organisation_id' => $organisation->id,
            'organiser_user_id' => $user->id,
            'reference_data' => $referenceData,
            'form_state_snapshot' => $state->toSnapshot(),
        ]);
    }

    /**
     * Resolves the URL of the initial statustype (volgnummer 1) for the
     * zaaktype so the local zaak has a valid statustype_url from creation.
     *
     * Without this the statustype_url stays empty until the async ZGW
     * round-trip (SetInitialStatusZGW -> webhook -> UpdateZaakReferenceData)
     * fills it, and the CreateConceptAdviceQuestions job dispatched on
     * `Zaak::created` runs in that window. Its system-generated thread message
     * triggers Thread::getParticipants(), which reads $zaak->statustype and
     * crashed on null. Setting the initial statustype here closes that window.
     *
     * Resolution failures must not block the submit: we fall back to an empty
     * string (the previous behaviour), and the webhook still fills it later.
     */
    private function resolveInitialStatustypeUrl(Zaaktype $zaaktype, ?string $versionUrl): string
    {
        $versionUrl = $versionUrl ?: $zaaktype->zgw_zaaktype_url;

        if (! $versionUrl) {
            return '';
        }

        try {
            $statustypen = Zgw::connection($zaaktype->zgwConnectionName())
                ->catalogi()->statustypen()
                ->index(['zaaktype' => $versionUrl])
                ->collect();

            $initieel = $statustypen->sortBy('volgnummer')->first();

            return $initieel ? ($initieel['url'] ?? '') : '';
        } catch (\Throwable $e) {
            Log::warning('CreateLocalZaak: kon initiële statustype niet bepalen', [
                'zaaktype_id' => $zaaktype->id,
                'zgw_zaaktype_url' => $versionUrl,
                'exception' => $e->getMessage(),
            ]);

            return '';
        }
    }
}
