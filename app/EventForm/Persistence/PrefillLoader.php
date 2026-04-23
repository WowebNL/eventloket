<?php

declare(strict_types=1);

namespace App\EventForm\Persistence;

use App\EventForm\State\FormState;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;

/**
 * Bouwt een FormState op uit een eerder ingediende `Zaak` voor de
 * "Herhaal aanvraag"-flow. De query-param `prefill_from_zaak` bevat het
 * UUID van de bron-zaak.
 *
 * De state komt in volgorde van betrouwbaarheid uit:
 *   1. `form_state_snapshot` (de complete snapshot bij submit) — als die
 *      er is, is dat de rijkste bron.
 *   2. Anders: platte `reference_data`-velden mappen naar de bekende
 *      FormState-keys (fallback voor oudere zaken zonder snapshot).
 *
 * Bij schemawijzigingen (veldnaam gewijzigd of veld verdwenen) kunnen er
 * waardes in de snapshot zitten die niet meer matchen met een huidige
 * veld-key. Die waardes worden stil overgeslagen — OF deed dat ook, en
 * de user vult bij "Herhaal" eventuele missende stukken handmatig aan.
 *
 * Eigenaarschap (Zaak hoort bij dezelfde organisatie als de ingelogde
 * user) wordt door `ValidatePrefillOwnership`-middleware afgedwongen
 * vóórdat deze klas wordt aangeroepen.
 */
class PrefillLoader
{
    public function load(
        ?string $zaakId,
        User $user,
        Organisation $organisation,
    ): ?FormState {
        if ($zaakId === null || $zaakId === '') {
            return null;
        }

        $zaak = Zaak::query()
            ->where('id', $zaakId)
            ->where('organisation_id', $organisation->id)
            ->first();

        if (! $zaak instanceof Zaak) {
            return null;
        }

        $snapshot = $zaak->form_state_snapshot;
        if (is_array($snapshot) && ! empty($snapshot)) {
            return $this->fromSnapshot($snapshot);
        }

        return $this->fromReferenceData($zaak);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function fromSnapshot(array $snapshot): FormState
    {
        $state = FormState::fromSnapshot($snapshot);

        // Alleen veld-waardes hergebruiken, geen afgeleide variabelen
        // (rules berekenen die opnieuw) of step-applicable-flags (die
        // horen bij de vorige submit). Snapshot kan die bevatten;
        // filteren houdt de prefill schoon.
        return $this->stripDerivedState($state);
    }

    private function fromReferenceData(Zaak $zaak): FormState
    {
        $state = FormState::empty();
        $ref = $zaak->reference_data;

        // Omgekeerde mapping van MapFormStateToReferenceData — waar
        // mogelijk: terug van reference_data-veld naar FormState-key.
        // Velden die niet 1-op-1 reversibel zijn (bv. registratiedatum)
        // slaan we over.
        $pairs = [
            'watIsDeNaamVanHetEvenementVergunning' => $ref->naam_evenement,
            'soortEvenement' => is_string($ref->types_evenement) ? $ref->types_evenement : null,
            'EvenementStart' => $ref->start_evenement,
            'EvenementEind' => $ref->eind_evenement,
            'OpbouwStart' => $ref->start_opbouw,
            'OpbouwEind' => $ref->eind_opbouw,
            'AfbouwStart' => $ref->start_afbouw,
            'AfbouwEind' => $ref->eind_afbouw,
            'watIsHetMaximaalAanwezigeAantalPersonenDatOpEnigMomentAanwezigKanZijnBijUwEvenementX' => $ref->aanwezigen,
        ];

        foreach ($pairs as $key => $value) {
            if ($value !== null && $value !== '') {
                $state->setField($key, $value);
            }
        }

        return $state;
    }

    /**
     * Knip afgeleide state eruit zodat de prefill een "leeg-met-invullen"
     * gevoel geeft, niet een "volgende submit"-gevoel.
     */
    private function stripDerivedState(FormState $state): FormState
    {
        $clean = $state->toSnapshot();
        // Step-applicability en hidden-overrides wissen: rules moeten die
        // opnieuw berekenen op basis van de nieuwe session-context.
        $clean['field_hidden'] = [];
        $clean['step_applicable'] = [];

        return FormState::fromSnapshot($clean);
    }
}
