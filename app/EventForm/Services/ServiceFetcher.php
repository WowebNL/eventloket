<?php

declare(strict_types=1);

namespace App\EventForm\Services;

use App\EventForm\State\FormState;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;

/**
 * Dispatcher voor de 4 OF `fetch-from-service`-calls. In plaats van HTTP-
 * calls naar onze eigen Passport-endpoints roepen we rechtstreeks de
 * service-klassen aan (die dezelfde logica bevatten) en schrijven we de
 * response terug naar de FormState-variabele. EventFormPage::updated()
 * roept dit per relevant veld aan; FormDerivedState pikt de nieuwe
 * waarden vanzelf op via `$state->get()`.
 */
class ServiceFetcher
{
    public function __construct(
        private readonly LocationServerCheckService $locationService,
        private readonly MunicipalityVariablesService $municipalityService,
        private readonly FormSessionService $sessionService,
        private readonly EventsCheckService $eventsService,
    ) {}

    /**
     * Per-state cache: voorkomt dat de fetch-rules bij elke
     * Livewire-roundtrip opnieuw dezelfde DB-queries +
     * intersect-calculations doen. Gekenmerkt op een hash van de
     * relevante input — verandert input → cache-miss → opnieuw fetchen.
     *
     * @var \WeakMap<FormState, array<string, string>>
     */
    private \WeakMap $inputHashByState;

    public function fetch(string $variable, FormState $state): void
    {
        $this->inputHashByState ??= new \WeakMap;
        $hashes = $this->inputHashByState[$state] ?? [];

        $newHash = $this->inputHashFor($variable, $state);
        if ($newHash !== null && ($hashes[$variable] ?? null) === $newHash) {
            return; // input ongewijzigd → resultaat staat al in state
        }

        match ($variable) {
            'eventloketSession' => $this->fetchEventloketSession($state),
            'gemeenteVariabelen' => $this->fetchGemeenteVariabelen($state),
            'evenementenInDeGemeente' => $this->fetchEvenementenInDeGemeente($state),
            'inGemeentenResponse' => $this->fetchInGemeentenResponse($state),
            default => null,
        };

        if ($newHash !== null) {
            $hashes[$variable] = $newHash;
            $this->inputHashByState[$state] = $hashes;
        }
    }

    /**
     * Hash van de inputs die deze fetch-variant beïnvloeden. Twee
     * roundtrips met dezelfde hash hoeven niet opnieuw te fetchen.
     */
    private function inputHashFor(string $variable, FormState $state): ?string
    {
        return match ($variable) {
            'eventloketSession' => null, // gebeurt 1× bij mount, geen cache nodig
            'gemeenteVariabelen' => sha1((string) $state->get('evenementInGemeente.brk_identification')),
            'evenementenInDeGemeente' => sha1(implode('|', [
                (string) $state->get('EvenementStart'),
                (string) $state->get('EvenementEind'),
                (string) $state->get('evenementInGemeente.brk_identification'),
            ])),
            'inGemeentenResponse' => sha1((string) json_encode([
                'p' => $state->get('locatieSOpKaart'),
                'l' => $state->get('routesOpKaart'),
                'a' => $state->get('adresVanDeGebouwEn'),
            ])),
            default => null,
        };
    }

    private function fetchEventloketSession(FormState $state): void
    {
        $user = $state->get('authUser');
        $org = $state->get('authOrganisation');
        if (! $user instanceof User || ! $org instanceof Organisation) {
            return;
        }

        $state->setVariable('eventloketSession', $this->sessionService->buildFor($user, $org));
    }

    private function fetchGemeenteVariabelen(FormState $state): void
    {
        $brkId = $state->get('evenementInGemeente.brk_identification');
        if (! is_string($brkId) || $brkId === '') {
            return;
        }

        /** @var Municipality|null $municipality */
        $municipality = Municipality::query()->where('brk_identification', $brkId)->first();
        if ($municipality === null) {
            return;
        }

        $state->setVariable(
            'gemeenteVariabelen',
            $this->municipalityService->forMunicipalityAsKeyValue($municipality),
        );
    }

    private function fetchEvenementenInDeGemeente(FormState $state): void
    {
        $start = $state->get('EvenementStart');
        $end = $state->get('EvenementEind');
        $brkId = $state->get('evenementInGemeente.brk_identification');

        if (! is_string($start) || $start === '') {
            return;
        }
        if (! is_string($end) || $end === '') {
            return;
        }
        if (! is_string($brkId) || $brkId === '') {
            return;
        }

        $result = $this->eventsService->check($start, $end, $brkId);
        $state->setVariable('evenementenInDeGemeente', $result['event_names']);
    }

    private function fetchInGemeentenResponse(FormState $state): void
    {
        $input = new LocationServerCheckInput(
            polygons: $this->collectPolygonsFromEditgrid($state->get('locatieSOpKaart')),
            line: null,
            lines: $this->collectLinesFromEditgrid($state->get('routesOpKaart')),
            addresses: $this->collectAddressesFromEditgrid($state->get('adresVanDeGebouwEn')),
            address: null,
        );

        if (! $input->hasAnyInput()) {
            return;
        }

        $state->setVariable('inGemeentenResponse', $this->locationService->execute($input));
    }

    /**
     * @return list<array{postcode: string, houseNumber: string}>|null
     */
    private function collectAddressesFromEditgrid(mixed $rows): ?array
    {
        if (! is_array($rows) || $rows === []) {
            return null;
        }

        $addresses = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $addr = $row['adresVanHetGebouwWaarUwEvenementPlaatsvindt1'] ?? null;
            if (! is_array($addr)) {
                continue;
            }
            $postcode = $addr['postcode'] ?? null;
            $huisnummer = $addr['huisnummer'] ?? null;
            if (is_string($postcode) && $postcode !== '' && ($huisnummer !== null && $huisnummer !== '')) {
                $addresses[] = [
                    'postcode' => $postcode,
                    'houseNumber' => (string) $huisnummer,
                ];
            }
        }

        return $addresses === [] ? null : $addresses;
    }

    /**
     * Pak alle GeoJSON-polygon-geometrieën uit `locatieSOpKaart`. We
     * ondersteunen twee shapes:
     *
     *   1. Nieuw (sinds Repeater-eruit): één Map-state direct,
     *      `{lat, lng, geojson: {features: [...]}}`. Map ondersteunt
     *      multi-feature; alle polygonen zitten in één state.
     *   2. Oud (Repeater-rows): `[{naamVanDeLocatieKaart, buitenLocatieVanHetEvenement: {...}}, ...]`.
     *      Backward-compat voor bestaande drafts die met de oude shape
     *      zijn opgeslagen.
     *
     * In beide gevallen pakken we `features[].geometry`-objecten — die
     * zijn zelf al GeoJSON-shapes (Polygon/MultiPolygon) die
     * `GeoJsonReader::read()` direct kan parsen.
     *
     * @return list<array<string, mixed>>|null
     */
    private function collectPolygonsFromEditgrid(mixed $value): ?array
    {
        if (! is_array($value) || $value === []) {
            return null;
        }

        // Nieuwe shape: één Map-state-object met geojson direct erin.
        if (isset($value['geojson'])) {
            return $this->extractFeatureGeometries($value) ?: null;
        }

        // Oude shape: Repeater-rows. Per rij ofwel direct een geojson
        // (zonder wrapper-key) of via `buitenLocatieVanHetEvenement`.
        $polygons = [];
        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }
            $map = $row['buitenLocatieVanHetEvenement'] ?? $row;
            if (is_array($map)) {
                array_push($polygons, ...$this->extractFeatureGeometries($map));
            }
        }

        return $polygons === [] ? null : $polygons;
    }

    /**
     * Pak `features[].geometry` uit een Map-state-object met shape
     * `{lat, lng, geojson: {features: [{geometry: {...}}, ...]}}`.
     *
     * @return list<array<string, mixed>>
     */
    private function extractFeatureGeometries(mixed $mapState): array
    {
        if (! is_array($mapState)) {
            return [];
        }
        $geojson = $mapState['geojson'] ?? null;
        if (! is_array($geojson)) {
            return [];
        }
        $features = $geojson['features'] ?? null;
        if (! is_array($features)) {
            return [];
        }
        $out = [];
        foreach ($features as $feature) {
            if (! is_array($feature)) {
                continue;
            }
            $geometry = $feature['geometry'] ?? null;
            if (! is_array($geometry) || ! isset($geometry['type'], $geometry['coordinates'])) {
                continue;
            }
            $out[] = $geometry;
        }

        return $out;
    }

    /**
     * Pak alle GeoJSON-line-geometrieën uit de `routesOpKaart`-Repeater.
     * Zelfde patroon als `collectPolygonsFromEditgrid()`: dotswan/
     * filament-map-picker schrijft Map-state als
     * `{lat, lng, geojson: {features: [...]}}` en
     * LocationServerCheckService verwacht GeoJSON-geometry-objecten
     * (Geometry, niet de Map-wrapper). We pakken `features[].geometry`
     * eruit zodat `GeoJsonReader::read()` 'm direct kan lezen.
     *
     * @return list<array<string, mixed>>|null
     */
    private function collectLinesFromEditgrid(mixed $value): ?array
    {
        if (! is_array($value) || $value === []) {
            return null;
        }

        // Nieuwe shape (sinds Route-Repeater eruit): direct een Map-state.
        if (isset($value['geojson'])) {
            return $this->extractFeatureGeometries($value) ?: null;
        }

        // Oude shape: Repeater-rows. Per rij óf een wrapper-key
        // `routeVanHetEvenement`, óf direct een Map-state.
        $lines = [];
        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }
            $route = $row['routeVanHetEvenement'] ?? $row;
            if (! is_array($route)) {
                continue;
            }
            $geojson = $route['geojson'] ?? null;
            if (! is_array($geojson)) {
                continue;
            }
            $features = $geojson['features'] ?? null;
            if (! is_array($features)) {
                continue;
            }
            foreach ($features as $feature) {
                if (! is_array($feature)) {
                    continue;
                }
                $geometry = $feature['geometry'] ?? null;
                if (! is_array($geometry) || ! isset($geometry['type'], $geometry['coordinates'])) {
                    continue;
                }
                $lines[] = $geometry;
            }
        }

        return $lines === [] ? null : $lines;
    }
}
