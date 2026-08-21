<?php

declare(strict_types=1);

namespace App\EventForm\Submit;

use App\EventForm\State\FormState;
use App\EventForm\Support\DagenRepeater;
use App\EventForm\Support\LocationKinds;
use App\Models\Organisation;
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
            locaties_evenement: $this->locatiesEvenement($state),
            dagen_evenement: $this->dagen($state, 'EvenementDagen'),
            dagen_opbouw: $this->dagen($state, 'OpbouwDagen'),
            dagen_afbouw: $this->dagen($state, 'AfbouwDagen'),
        );
    }

    /**
     * Per-day start and end times, filled in only when the period runs over
     * several calendar days. Null keeps the envelope the whole story.
     *
     * @return list<array{datum: string, start: string, eind: string}>|null
     */
    private function dagen(FormState $state, string $key): ?array
    {
        $rijen = $state->get($key);

        if (! is_array($rijen)) {
            return null;
        }

        $blokken = DagenRepeater::naarReferenceData($rijen);

        return $blokken !== [] ? $blokken : null;
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

    private function locatiesEvenement(FormState $state): ?string
    {
        $names = [];

        // Read every location field through `LocationKinds` so that state left
        // behind by a kind the organiser unticked (Filament keeps a hidden
        // field's value; see `LocationKinds`) does not leak a copied-over
        // source event's location into the local reference_data.
        $gebouwen = LocationKinds::valueFor($state, LocationKinds::GEBOUW);
        if (is_array($gebouwen)) {
            foreach ($gebouwen as $entry) {
                if (is_array($entry) && ! empty($entry['naamVanDeLocatieGebouw'])) {
                    $names[] = (string) $entry['naamVanDeLocatieGebouw'];
                }
            }
        }

        // The map/route name fields hang on the "buiten" resp. "route" kind:
        // both are hidden together with their kind's component, so their
        // leftover value counts only while that kind is ticked.
        $kaart = LocationKinds::isSelected($state, LocationKinds::BUITEN)
            ? $this->stringOrNull($state->get('naamVanDeLocatieKaart'))
            : null;
        if ($kaart !== null) {
            $names[] = $kaart;
        }

        $route = LocationKinds::isSelected($state, LocationKinds::ROUTE)
            ? $this->stringOrNull($state->get('naamVanDeRoute'))
            : null;
        if ($route !== null) {
            $names[] = $route;
        }

        return $names !== [] ? implode(', ', $names) : null;
    }

    private function naamLocatie(FormState $state): ?string
    {
        // Gebouw-tak: eerste naam uit adresVanDeGebouwEn, alleen zolang de
        // gebouw-soort is aangevinkt (LocationKinds houdt afgevinkte state weg).
        $gebouwen = LocationKinds::valueFor($state, LocationKinds::GEBOUW);
        if (is_array($gebouwen)) {
            foreach ($gebouwen as $entry) {
                if (is_array($entry) && ! empty($entry['naamVanDeLocatieGebouw'])) {
                    return (string) $entry['naamVanDeLocatieGebouw'];
                }
            }
        }

        // Generic fallback name migrated from Open Formulieren
        // (`watIsDeNaamVanDeLocatieSWaarUwEvenementPlaatsvindt` → `naamVanDeLocatie`,
        // see `BackfillSnapshotsFromObjects`). It is not bound to a single kind
        // and is not hidden by unticking one, so it stays a direct read.
        return $this->stringOrNull($state->get('naamVanDeLocatie'));
    }

    private function organisator(FormState $state): ?string
    {
        $user = $state->get('authUser');
        $org = $state->get('authOrganisation');

        if (is_object($org) && isset($org->name)
            && ! ($org instanceof Organisation && $org->isPersonal())) {
            return (string) $org->name;
        }
        if (is_object($user) && isset($user->name)) {
            return (string) $user->name;
        }

        return $this->stringOrNull($state->get('organisator'));
    }
}
