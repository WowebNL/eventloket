<?php

declare(strict_types=1);

namespace App\EventForm\Submit;

use App\EventForm\State\FormState;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Carbon\Carbon;

/**
 * Bouwt een ZaakReferenceData-VO op basis van de FormState van een
 * ingediende aanvraag. Velden die niet in de state zitten worden
 * overgeslagen (VO accepteert ze als null).
 *
 * Alle veld-keys komen rechtstreeks uit de 17 step-klassen in
 * app/EventForm/Schema/Steps/. Bij schemawijzigingen hoeft hier
 * niets te veranderen zolang de keys blijven; de VO neemt ze op
 * via de constructor.
 */
final class MapFormStateToReferenceData
{
    public function build(FormState $state, string $statusName, string $statustypeUrl): ZaakReferenceData
    {
        return new ZaakReferenceData(
            start_evenement: $this->iso8601($state->get('EvenementStart')),
            eind_evenement: $this->iso8601($state->get('EvenementEind')),
            registratiedatum: Carbon::now('Europe/Amsterdam')->toIso8601String(),
            status_name: $statusName,
            statustype_url: $statustypeUrl,
            risico_classificatie: $this->stringOrNull($state->get('risicoClassificatie')),
            naam_locatie_eveneme: $this->naamLocatie($state),
            naam_evenement: $this->stringOrNull($state->get('watIsDeNaamVanHetEvenementVergunning')),
            organisator: $this->organisator($state),
            aanwezigen: $this->stringOrNull($state->get('watIsHetMaximaalAanwezigeAantalPersonenDatOpEnigMomentAanwezigKanZijnBijUwEvenementX')),
            types_evenement: $this->stringOrNull($state->get('soortEvenement')),
            risico_toelichting: $this->stringOrNull($state->get('risicoToelichting')),
            start_opbouw: $this->iso8601OrNull($state->get('OpbouwStart')),
            eind_opbouw: $this->iso8601OrNull($state->get('OpbouwEind')),
            start_afbouw: $this->iso8601OrNull($state->get('AfbouwStart')),
            eind_afbouw: $this->iso8601OrNull($state->get('AfbouwEind')),
        );
    }

    private function iso8601(mixed $value): string
    {
        return $this->iso8601OrNull($value) ?? Carbon::now('Europe/Amsterdam')->toIso8601String();
    }

    private function iso8601OrNull(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value, 'Europe/Amsterdam')->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return null;
    }

    private function naamLocatie(FormState $state): ?string
    {
        // Gebouw-tak: eerste naam uit adresVanDeGebouwEn.
        $gebouwen = $state->get('adresVanDeGebouwEn');
        if (is_array($gebouwen)) {
            foreach ($gebouwen as $entry) {
                if (is_array($entry) && ! empty($entry['naamVanDeLocatieGebouw'])) {
                    return (string) $entry['naamVanDeLocatieGebouw'];
                }
            }
        }

        // Buiten/route-tak: eventuele opgegeven naam.
        return $this->stringOrNull($state->get('naamVanDeLocatie'));
    }

    private function organisator(FormState $state): ?string
    {
        $user = $state->get('authUser');
        $org = $state->get('authOrganisation');

        if (is_object($org) && isset($org->name)) {
            return (string) $org->name;
        }
        if (is_object($user) && isset($user->name)) {
            return (string) $user->name;
        }

        return $this->stringOrNull($state->get('organisator'));
    }
}
