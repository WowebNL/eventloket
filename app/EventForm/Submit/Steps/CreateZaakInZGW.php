<?php

declare(strict_types=1);

namespace App\EventForm\Submit\Steps;

use App\EventForm\State\FormState;
use App\Models\Zaaktype;
use App\Services\Zgw\ZgwResource;
use App\ValueObjects\OzZaak;
use Carbon\Carbon;
use Woweb\Zgw\Facades\Zgw;

/**
 * Synchrone eerste ZGW-stap van een submit: maakt een basiszaak aan bij
 * OpenZaak zodat we direct een zaaknummer terug hebben. Alle verrijking
 * (eigenschappen, einddatum, initiator, geometry, doorkomsten) gaat
 * daarna async via queue-jobs.
 *
 * Vervangt wat in de oude OF-flow door Open Forms zelf gedaan werd.
 */
final class CreateZaakInZGW
{
    public function execute(FormState $state, Zaaktype $zaaktype): OzZaak
    {
        $payload = [
            'zaaktype' => $zaaktype->zgw_zaaktype_url,
            'bronorganisatie' => $this->bronorganisatie($state, $zaaktype),
            'verantwoordelijkeOrganisatie' => $this->bronorganisatie($state, $zaaktype),
            'startdatum' => Carbon::now('Europe/Amsterdam')->toDateString(),
            'registratiedatum' => Carbon::now('Europe/Amsterdam')->toDateString(),
            'omschrijving' => $this->omschrijving($state),
            'toelichting' => $this->toelichting($state),
        ];

        $data = Zgw::connection($zaaktype->zgwConnectionName())->zaken()->zaken()->store($payload);

        return new OzZaak(...ZgwResource::ensureUuid($data));
    }

    private function omschrijving(FormState $state): string
    {
        $naam = $state->get('watIsDeNaamVanHetEvenementVergunning');

        return is_string($naam) && $naam !== '' ? mb_substr($naam, 0, 80) : 'Evenement-aanvraag';
    }

    private function toelichting(FormState $state): string
    {
        $omschrijving = $state->get('geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning');

        return is_string($omschrijving) ? mb_substr($omschrijving, 0, 1000) : '';
    }

    private function bronorganisatie(FormState $state, Zaaktype $zaaktype): string
    {
        // In de OF-flow was in alle 45 registratie-backends dezelfde RSIN
        // hardcoded (820151130 = Veiligheidsregio Zuid-Limburg). We nemen
        // diezelfde conventie over: één centrale config-waarde. Doorkomst-
        // subzaken voor individuele gemeenten erven via CreateDoorkomstZaken
        // dezelfde waarde (alleen 't zaaktype verschilt).
        return (string) config('services.openzaak.bronorganisatie_rsin', '820151130');
    }
}
