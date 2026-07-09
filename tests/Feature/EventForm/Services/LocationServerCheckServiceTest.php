<?php

declare(strict_types=1);

use App\EventForm\Services\LocationServerCheckInput;
use App\EventForm\Services\LocationServerCheckService;
use App\Models\Municipality;
use App\Services\LocatieserverService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->municipality = Municipality::factory()->create([
        'brk_identification' => 'GM0882',
        'name' => 'Maastricht',
    ]);
});

test('empty input yields empty response skeleton', function () {
    $service = new LocationServerCheckService;

    $result = $service->execute(new LocationServerCheckInput);

    expect($result)
        ->toHaveKeys(['all', 'polygons', 'line', 'lines', 'addresses'])
        ->and($result['all']['items'])->toBe([])
        ->and($result['all']['within'])->toBeNull();
});

test('address input resolves to municipality via locatieserver', function () {
    // Fake PDOK locatieserver response
    Http::fake([
        'api.pdok.nl/bzk/locatieserver/search/v3_1/free*' => Http::response([
            'response' => [
                'docs' => [
                    [
                        'gemeentecode' => '0882',
                        'gemeentenaam' => 'Maastricht',
                    ],
                ],
            ],
        ]),
    ]);

    $service = new LocationServerCheckService(new LocatieserverService);

    $result = $service->execute(new LocationServerCheckInput(
        address: ['postcode' => '6211AA', 'houseNumber' => '1'],
    ));

    expect($result['addresses']['within'])->toBeTrue()
        ->and($result['all']['within'])->toBeTrue()
        ->and($result['all']['items'])->toHaveCount(1);

    $item = $result['all']['items'][0];
    expect($item['brk_identification'])->toBe('GM0882')
        ->and($item['name'])->toBe('Maastricht');
});

test('unknown postcode yields within=false and empty items', function () {
    Http::fake([
        'api.pdok.nl/bzk/locatieserver/search/v3_1/free*' => Http::response([
            'response' => ['docs' => []],
        ]),
    ]);

    $service = new LocationServerCheckService(new LocatieserverService);

    $result = $service->execute(new LocationServerCheckInput(
        address: ['postcode' => '9999ZZ', 'houseNumber' => '1'],
    ));

    expect($result['addresses']['within'])->toBeFalse()
        ->and($result['all']['within'])->toBeFalse()
        ->and($result['all']['items'])->toBe([]);
});

test('a degenerate polygon is skipped instead of crashing the engine', function () {
    // Een half-getekende polygoon (ring met minder dan vier punten) liet
    // PostGIS crashen ("Polygon must have at least four points in each ring"),
    // wat als 500 tijdens de Livewire-update naar buiten kwam (Sentry
    // EVENTLOKET-Z). De service slaat zo'n geometrie nu over.
    $service = new LocationServerCheckService;

    $result = $service->execute(new LocationServerCheckInput(
        polygons: [
            ['type' => 'Polygon', 'coordinates' => [[[0.5, 0.5], [0.5, 1.5], [0.5, 0.5]]]],
        ],
    ));

    expect($result['polygons']['items'])->toHaveCount(0)
        ->and($result['all']['items'])->toBe([]);
});

test('a valid polygon is still processed by the engine', function () {
    $service = new LocationServerCheckService;

    $result = $service->execute(new LocationServerCheckInput(
        polygons: [
            ['type' => 'Polygon', 'coordinates' => [[[0.5, 0.5], [0.5, 1.5], [1.5, 1.5], [1.5, 0.5], [0.5, 0.5]]]],
        ],
    ));

    expect($result['polygons']['items'])->toHaveCount(1)
        ->and($result['polygons']['items']->first()['brk_identification'])->toBe('GM0882');
});
