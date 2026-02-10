<?php

use App\Support\Helpers\ArrayHelper;

test('findElementWithKey finds element at top level', function () {
    $data = [
        'name' => 'John',
        'coordinates' => [1, 2],
    ];

    $result = ArrayHelper::findElementWithKey($data, 'coordinates');

    expect($result)->toBe($data);
    expect($result['coordinates'])->toBe([1, 2]);
});

test('findElementWithKey finds element in nested array', function () {
    $data = [
        'name' => 'Location',
        'details' => [
            'type' => 'Polygon',
            'coordinates' => [[1, 2], [3, 4]],
        ],
    ];

    $result = ArrayHelper::findElementWithKey($data, 'coordinates');

    expect($result)->toBe($data['details']);
    expect($result['coordinates'])->toBe([[1, 2], [3, 4]]);
});

test('findElementWithKey finds element in deeply nested array', function () {
    $data = [
        'locations' => [
            [
                'name' => 'First',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [5.5, 52.1],
                ],
            ],
        ],
    ];

    $result = ArrayHelper::findElementWithKey($data, 'coordinates');

    expect($result)->toBe($data['locations'][0]['geometry']);
    expect($result['coordinates'])->toBe([5.5, 52.1]);
});

test('findElementWithKey returns null when key not found', function () {
    $data = [
        'name' => 'John',
        'age' => 30,
    ];

    $result = ArrayHelper::findElementWithKey($data, 'coordinates');

    expect($result)->toBeNull();
});

test('findElementWithKey returns null for non-array input', function () {
    $result = ArrayHelper::findElementWithKey('not an array', 'key');

    expect($result)->toBeNull();
});

test('findElementWithKey finds postcode in nested structure', function () {
    $data = [
        [
            'naamVanDeLocatieGebouw' => "d'n oude klapschaats",
            'adresVanHetGebouwWaarUwEvenementPlaatsvindt1' => [
                'city' => 'Cadier en Keer',
                'postcode' => '6267 ec',
                'streetName' => 'Kerkstraat',
            ],
        ],
    ];

    $result = ArrayHelper::findElementWithKey($data, 'postcode');

    expect($result)->toBe($data[0]['adresVanHetGebouwWaarUwEvenementPlaatsvindt1']);
    expect($result['postcode'])->toBe('6267 ec');
});
