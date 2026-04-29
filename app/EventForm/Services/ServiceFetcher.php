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
 * service-klassen aan (die dezelfde logica bevatten), schrijven we de
 * response terug naar de FormState-variabele en triggeren we zo de
 * volgende RulesEngine-pass.
 */
class ServiceFetcher
{
    public function __construct(
        private readonly LocationServerCheckService $locationService,
        private readonly MunicipalityVariablesService $municipalityService,
        private readonly FormSessionService $sessionService,
        private readonly EventsCheckService $eventsService,
    ) {}

    public function fetch(string $variable, FormState $state): void
    {
        match ($variable) {
            'eventloketSession' => $this->fetchEventloketSession($state),
            'gemeenteVariabelen' => $this->fetchGemeenteVariabelen($state),
            'evenementenInDeGemeente' => $this->fetchEvenementenInDeGemeente($state),
            'inGemeentenResponse' => $this->fetchInGemeentenResponse($state),
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
     * Pak alle GeoJSON-polygon-geometrieën uit de `locatieSOpKaart`-
     * Repeater. Elke rij heeft een `buitenLocatieVanHetEvenement` Map-
     * state in het formaat `{lat, lng, geojson: {features: [...]}}`. We
     * pakken `features[].geometry`-objecten die zelf al GeoJSON-shapes
     * (Polygon/MultiPolygon) zijn, en geven 'm zo door aan
     * LocationServerCheckService.
     *
     * @return list<array<string, mixed>>|null
     */
    private function collectPolygonsFromEditgrid(mixed $rows): ?array
    {
        if (! is_array($rows) || $rows === []) {
            return null;
        }

        $polygons = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $map = $row['buitenLocatieVanHetEvenement'] ?? null;
            if (! is_array($map)) {
                continue;
            }
            $geojson = $map['geojson'] ?? null;
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
                $polygons[] = $geometry;
            }
        }

        return $polygons === [] ? null : $polygons;
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
    private function collectLinesFromEditgrid(mixed $rows): ?array
    {
        if (! is_array($rows) || $rows === []) {
            return null;
        }

        $lines = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $route = $row['routeVanHetEvenement'] ?? null;
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
