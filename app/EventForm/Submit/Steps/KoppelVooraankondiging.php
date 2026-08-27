<?php

declare(strict_types=1);

namespace App\EventForm\Submit\Steps;

use App\Enums\ZaakRelatieType;
use App\EventForm\Schema\Steps\Vragenboom2Step;
use App\EventForm\State\FormState;
use App\EventForm\Submit\DetermineAanvraagType;
use App\Models\Organisation;
use App\Models\Zaak;
use App\Models\ZaakRelatie;
use Illuminate\Support\Facades\Log;

/**
 * Synchronous submit step for issue #10: when the organiser linked a
 * vooraankondiging to this aanvraag, write the typed relation row
 * ("this aanvraag replaces that vooraankondiging").
 *
 * The form values come from the client, so everything is re-checked
 * server-side: the link only applies on the vergunning path (answer 8),
 * the source must belong to the same organisation and must actually be
 * a vooraankondiging. A failing guard skips the link with a warning
 * instead of failing the submit — the zaak already exists in ZGW at
 * this point and a missing link is repairable, a lost aanvraag is not.
 */
final class KoppelVooraankondiging
{
    public function __construct(private readonly DetermineAanvraagType $determineAanvraagType) {}

    public function execute(FormState $state, Zaak $zaak, Organisation $organisation): void
    {
        if ($state->get(Vragenboom2Step::HEEFT_VOORAANKONDIGING_FIELD) !== 'Ja') {
            return;
        }

        $bronId = $state->get(Vragenboom2Step::VOORAANKONDIGING_ZAAK_FIELD);
        if (! is_string($bronId) || $bronId === '') {
            return;
        }

        // Only a vergunningaanvraag replaces a vooraankondiging; a melding
        // or another vooraankondiging never gets the link (answer 8).
        if ($this->determineAanvraagType->forState($state) !== DetermineAanvraagType::VERGUNNING) {
            return;
        }

        if ($bronId === $zaak->id) {
            return;
        }

        $bron = Zaak::query()
            ->whereKey($bronId)
            ->where('organisation_id', $organisation->id)
            ->first();

        if (! $bron instanceof Zaak) {
            Log::warning('KoppelVooraankondiging: bron-zaak niet gevonden of niet van deze organisatie', [
                'zaak_id' => $zaak->id,
                'bron_zaak_id' => $bronId,
                'organisation_id' => $organisation->id,
            ]);

            return;
        }

        if (! $bron->isVooraankondiging()) {
            Log::warning('KoppelVooraankondiging: bron-zaak is geen vooraankondiging', [
                'zaak_id' => $zaak->id,
                'bron_zaak_id' => $bron->id,
                'bron_zaaktype' => $bron->zaaktype?->name,
            ]);

            return;
        }

        // A vooraankondiging is replaced at most once. The form already
        // excludes linked ones from the select; this catches a stale
        // draft or a manipulated value racing a concurrent submit.
        if ($bron->opgevolgdDoor()->exists()) {
            Log::warning('KoppelVooraankondiging: vooraankondiging heeft al een definitieve aanvraag', [
                'zaak_id' => $zaak->id,
                'bron_zaak_id' => $bron->id,
            ]);

            return;
        }

        ZaakRelatie::query()->firstOrCreate([
            'zaak_id' => $zaak->id,
            'gerelateerde_zaak_id' => $bron->id,
            'type' => ZaakRelatieType::VervangtVooraankondiging->value,
        ]);
    }
}
