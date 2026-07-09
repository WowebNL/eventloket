<?php

declare(strict_types=1);

use App\EventForm\Validation\CompleteMapGeometry;

function completeMapGeometryFails(mixed $value): bool
{
    $failed = false;
    (new CompleteMapGeometry)->validate('kaart', $value, function () use (&$failed) {
        $failed = true;
    });

    return $failed;
}

function mapStateWithGeometry(array $geometry): array
{
    return [
        'lat' => 50.85,
        'lng' => 5.69,
        'geojson' => ['features' => [['type' => 'Feature', 'geometry' => $geometry]]],
    ];
}

test('a complete polygon passes validation', function () {
    $value = mapStateWithGeometry([
        'type' => 'Polygon',
        'coordinates' => [[[0.5, 0.5], [0.5, 1.5], [1.5, 1.5], [1.5, 0.5], [0.5, 0.5]]],
    ]);

    expect(completeMapGeometryFails($value))->toBeFalse();
});

test('an incomplete shape fails validation', function (array $geometry) {
    expect(completeMapGeometryFails(mapStateWithGeometry($geometry)))->toBeTrue();
})->with([
    'polygon with three ring positions' => [['type' => 'Polygon', 'coordinates' => [[[0.5, 0.5], [0.5, 1.5], [0.5, 0.5]]]]],
    'line with one position' => [['type' => 'LineString', 'coordinates' => [[0, 0]]]],
]);

test('an incomplete polygon fails when the map state is a json string', function () {
    $value = json_encode(mapStateWithGeometry([
        'type' => 'Polygon',
        'coordinates' => [[[0.5, 0.5], [0.5, 1.5], [0.5, 0.5]]],
    ]));

    expect(completeMapGeometryFails($value))->toBeTrue();
});

test('a present-but-shapeless map fails validation (nothing complete drawn)', function (mixed $value) {
    expect(completeMapGeometryFails($value))->toBeTrue();
})->with([
    // Placeholder that selecting "buiten"/"route" seeds into the field.
    'repeater placeholder' => [['6dd157f3-f46e-4465-b4aa-5916da0c6b4f' => []]],
    'empty feature collection' => [['lat' => 50.85, 'lng' => 5.69, 'geojson' => ['features' => []]]],
    'centre only, nothing drawn' => [['lat' => 50.85, 'lng' => 5.69]],
]);

test('a genuinely empty value passes (the field\'s own required rule handles it)', function (mixed $value) {
    expect(completeMapGeometryFails($value))->toBeFalse();
})->with([
    'null' => null,
    'empty string' => '',
    'empty array' => [[]],
]);
