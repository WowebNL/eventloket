<?php

declare(strict_types=1);

namespace App\EventForm\Submit;

use App\EventForm\State\FormState;

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

        // formsubmission_id: OF gebruikte hiervoor `submission.kenmerk` —
        // wij hebben geen submission-object, dus dit wordt het lokale
        // zaak-public_id (= OpenZaak identificatie). Wordt door de caller
        // toegevoegd want op build-tijd is dat nog niet bekend.

        return $out;
    }

    /**
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
            'organisatie_adres' => $state->get('watIsHetAdresVanUwOrganisatie'),
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
            'multipolygons' => $state->get('locatieSOpKaart'),
            'line' => $state->get('routesOpKaart'),
            'bag_addresses' => $state->get('adresVanDeGebouwEn'),
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
