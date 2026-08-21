<?php

declare(strict_types=1);

namespace App\EventForm\Submit;

use App\EventForm\State\FormState;
use App\EventForm\Support\LocationKinds;

/**
 * Mapping tussen FormState-veldnamen en ZGW-zaakeigenschap-namen.
 *
 * Deze mapping stond in OF's `zgw-create-zaak`-registratie-backends
 * in het `content_json`-template: elke `{{ variables.X }}` placeholder
 * verwees naar een formio-component-key (die ook als variable-naam
 * dienst deed). Onze FormState gebruikt diezelfde keys, dus we kunnen
 * rechtstreeks `$state->get('EvenementStart')` lezen.
 *
 * 11 zaakeigenschappen + initiator + event_location — exact zoals OF
 * naar Objects API schreef, alleen nu rechtstreeks uit FormState.
 *
 * Bij een missende waarde of niet-bestaande eigenschap in de catalogus
 * van het zaaktype: stil overslaan (zo deed OF het ook — zie oude
 * `AddZaakeigenschappenZGW::handle()`, die `continue`de op een
 * ontbrekende `$catalogiEigenschap`).
 */
final class ZaakeigenschappenMap
{
    /**
     * @var array<string, string> eigenschap-naam → FormState-veld-key
     */
    private const EIGENSCHAPPEN = [
        'start_evenement' => 'EvenementStart',
        'eind_evenement' => 'EvenementEind',
        'start_opbouw' => 'OpbouwStart',
        'eind_opbouw' => 'OpbouwEind',
        'start_afbouw' => 'AfbouwStart',
        'eind_afbouw' => 'AfbouwEind',
        'naam_evenement' => 'watIsDeNaamVanHetEvenementVergunning',
        'types_evenement' => 'soortEvenement',
        'aanwezigen' => 'aantalVerwachteAanwezigen',
        'risico_classificatie' => 'risicoClassificatie',
    ];

    /**
     * The logical eigenschap keys this map can emit: the FormState-derived
     * ones plus `locaties_evenement`, which is composed by the caller. These are
     * the keys a per-municipality blueprint maps to concrete ZGW eigenschap namen.
     *
     * @return list<string>
     */
    public static function logicalKeys(): array
    {
        return [...array_keys(self::EIGENSCHAPPEN), 'locaties_evenement'];
    }

    /**
     * The default (identity) eigenschap_map: our own OpenZaak names every
     * eigenschap exactly like its logical key. Used to seed the blueprint.
     *
     * @return array<string, string>
     */
    public static function defaultEigenschapMap(): array
    {
        return array_combine(self::logicalKeys(), self::logicalKeys());
    }

    /**
     * Bouwt de lijst zaakeigenschappen uit de FormState. Format matcht
     * het oude Objects-API-record: `[{"naam_evenement": "..."}, ...]`.
     * Entries met lege waarde worden weggelaten — OF's `AddZaakeigenschappenZGW`
     * sloeg lege waarden al over op regel 50-51 van de oude job.
     *
     * @return list<array<string, scalar|array<int|string, mixed>>>
     */
    public function buildEigenschappen(FormState $state): array
    {
        $out = [];
        foreach (self::EIGENSCHAPPEN as $eigenschap => $key) {
            $value = $state->get($key);
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $out[] = [$eigenschap => $value];
        }

        $locaties = $this->buildLocatiesEvenement($state);
        if ($locaties !== null && $locaties !== '') {
            $out[] = ['locaties_evenement' => $locaties];
        }

        return $out;
    }

    private function buildLocatiesEvenement(FormState $state): ?string
    {
        $names = [];

        // Read every location field through `LocationKinds` so that state left
        // behind by a kind the organiser unticked (Filament keeps a hidden
        // field's value; see `LocationKinds`) does not leak a copied-over
        // source event's location into the zaakeigenschappen.
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

    /**
     * The natuurlijk_persoon_adres entry is assembled from the flat fields of
     * the "Adresgegevens" fieldset (only shown to aanvragers without a KvK
     * number), so a private aanvrager's verblijfsadres reaches the ZGW rol.
     *
     * @return array<string, mixed>
     */
    public function buildInitiator(FormState $state): array
    {
        $voornaam = $state->get('watIsUwVoornaam');
        $achternaam = $state->get('watIsUwAchternaam');
        $naam = trim(((string) $voornaam).' '.((string) $achternaam));

        return array_filter([
            'kvk' => $this->stringOrNull($state->get('watIsHetKamerVanKoophandelNummerVanUwOrganisatie')),
            'organisatie_naam' => $this->stringOrNull($state->get('watIsDeNaamVanUwOrganisatie')),
            'natuurlijk_persoon_adres' => array_filter([
                'postcode' => $this->stringOrNull($state->get('postcode')),
                'huisnummer' => $this->stringOrNull($state->get('huisnummer')),
                'huisletter' => $this->stringOrNull($state->get('huisletter')),
                'huisnummertoevoeging' => $this->stringOrNull($state->get('huisnummertoevoeging')),
                'straatnaam' => $this->stringOrNull($state->get('straatnaam')),
                'plaatsnaam' => $this->stringOrNull($state->get('plaatsnaam')),
                'land' => $this->stringOrNull($state->get('land')),
            ]),
            'contactpersoon' => array_filter([
                'naam' => $naam !== '' ? $naam : null,
                'emailadres' => $this->stringOrNull($state->get('watIsUwEMailadres')),
                'telefoonnummer' => $this->stringOrNull($state->get('watIsUwTelefoonnummer')),
            ]),
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildEventLocation(FormState $state): array
    {
        return array_filter([
            // Geometry and address fields: read through `LocationKinds` so an
            // unticked kind's leftover polygon/line/address does not travel to
            // the zaakgeometrie (`AddGeometryZGW`/`CreateDoorkomstZaken`).
            'multipolygons' => LocationKinds::valueFor($state, LocationKinds::BUITEN),
            'line' => LocationKinds::valueFor($state, LocationKinds::ROUTE),
            'bag_addresses' => LocationKinds::valueFor($state, LocationKinds::GEBOUW),
            // `watIsDeNaamVanDeLocatieSWaarUwEvenementPlaatsvindt` is the generic
            // event-location name migrated from Open Formulieren (see
            // `BackfillSnapshotsFromObjects`); it is not bound to a single kind
            // and is not hidden by unticking one, so it stays a direct read.
            'name' => $this->stringOrNull($state->get('watIsDeNaamVanDeLocatieSWaarUwEvenementPlaatsvindt')),
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
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
}
