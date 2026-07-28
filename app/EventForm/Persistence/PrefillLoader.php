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
 * user) wordt afgedwongen via ->where('organisation_id', $organisation->id)
 * in load(). Verwijder die where-clause NIET: er is geen middleware die dit overneemt.
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
        $state = FormState::fromSnapshot($this->stripHashedValues($snapshot));

        // Alleen veld-waardes hergebruiken, geen afgeleide variabelen
        // (rules berekenen die opnieuw) of step-applicable-flags (die
        // horen bij de vorige submit). Snapshot kan die bevatten;
        // filteren houdt de prefill schoon.
        return $this->stripDerivedState($state);
    }

    /**
     * Verwijdert gehashte waarden (prefix `hash:`) uit de snapshot vóórdat
     * de FormState wordt gebouwd. Na HashIdentifyingAttributes bevatten
     * gevoelige velden (KvK, BSN) een HMAC-hash — dat zijn geen bruikbare
     * voorinvulwaarden. Door ze te wissen krijgt applySessionPrefill() in
     * EventFormPage de kans om het KvK-veld alsnog vanuit de sessie te vullen,
     * en BSN-velden blijven leeg zodat de gebruiker ze opnieuw invult.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function stripHashedValues(array $snapshot): array
    {
        if (isset($snapshot['values']) && is_array($snapshot['values'])) {
            $snapshot['values'] = array_filter(
                $snapshot['values'],
                fn (mixed $value): bool => ! (is_string($value) && str_starts_with($value, 'hash:')),
            );
        }

        return $snapshot;
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
     * Location-dependent state from the source zaak. These are fetch results and
     * a choice made for the source event's location, not answers the organiser
     * gave: carrying them over would let the copy be routed to the original
     * municipality even after the location is changed. They are rebuilt from the
     * new location by the location gate.
     *
     * `evenementInGemeente` is derived, never written by the current form, but is
     * stripped defensively: a materialised value in the values bag would win
     * whenever the derivation yields null.
     *
     * @var list<string>
     */
    private const LOCATION_DEPENDENT_KEYS = [
        'userSelectGemeente',
        'inGemeentenResponse',
        'gemeenteVariabelen',
        'evenementenInDeGemeente',
        'evenementInGemeente',
    ];

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

        if (isset($clean['values']) && is_array($clean['values'])) {
            foreach (self::LOCATION_DEPENDENT_KEYS as $key) {
                unset($clean['values'][$key]);
            }

            $clean['values'] = $this->stripAddressBrkGemeente($clean['values']);
        }

        return FormState::fromSnapshot($clean);
    }

    /**
     * Drops the hidden `brkGemeente` the PDOK auto-fill stored on each copied
     * address row. It identifies the municipality of the *source* address, and
     * the location check trusts it verbatim, so a copied row whose auto-fill
     * does not re-fire would keep routing the aanvraag to the old municipality.
     * Clearing it forces a fresh postcode + house number lookup.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function stripAddressBrkGemeente(array $values): array
    {
        $addresses = $values['adresVanDeGebouwEn'] ?? null;
        if (! is_array($addresses)) {
            return $values;
        }

        foreach ($addresses as $index => $row) {
            if (is_array($row)) {
                $addresses[$index] = $this->withoutBrkGemeente($row);
            }
        }

        $values['adresVanDeGebouwEn'] = $addresses;

        return $values;
    }

    /**
     * Removes every `brkGemeente` entry from a copied address row, at whatever
     * depth it sits. The value does not live on the row itself but under the
     * AddressNL fieldset prefix (currently
     * `adresVanHetGebouwWaarUwEvenementPlaatsvindt1.brkGemeente`), so clearing
     * the row's own keys would miss it entirely. Walking the row instead of
     * hardcoding that prefix keeps this working when the schema key changes.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function withoutBrkGemeente(array $row): array
    {
        unset($row['brkGemeente']);

        foreach ($row as $key => $value) {
            if (is_array($value)) {
                $row[$key] = $this->withoutBrkGemeente($value);
            }
        }

        return $row;
    }
}
