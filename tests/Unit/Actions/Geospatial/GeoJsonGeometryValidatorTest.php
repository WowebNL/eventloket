<?php

declare(strict_types=1);

use App\Actions\Geospatial\GeoJsonGeometryValidator;

test('a polygon with enough ring positions is processable', function () {
    $polygon = [
        'type' => 'Polygon',
        'coordinates' => [[[0.5, 0.5], [0.5, 1.5], [1.5, 1.5], [1.5, 0.5], [0.5, 0.5]]],
    ];

    expect(GeoJsonGeometryValidator::isProcessable($polygon))->toBeTrue();
});

test('a polygon whose ring is degenerate is not processable', function (array $ring) {
    $polygon = ['type' => 'Polygon', 'coordinates' => [$ring]];

    expect(GeoJsonGeometryValidator::isProcessable($polygon))->toBeFalse();
})->with([
    'two positions' => [[[0.5, 0.5], [0.5, 1.5]]],
    'three positions' => [[[0.5, 0.5], [0.5, 1.5], [0.5, 0.5]]],
    'empty ring' => [[]],
]);

test('a multipolygon is processable only when every ring is valid', function () {
    $valid = [
        'type' => 'MultiPolygon',
        'coordinates' => [[[[0, 0], [0, 2], [2, 2], [2, 0], [0, 0]]]],
    ];
    $invalid = [
        'type' => 'MultiPolygon',
        'coordinates' => [[[[0, 0], [0, 2], [0, 0]]]],
    ];

    expect(GeoJsonGeometryValidator::isProcessable($valid))->toBeTrue()
        ->and(GeoJsonGeometryValidator::isProcessable($invalid))->toBeFalse();
});

test('a line is processable only with at least two positions', function () {
    $valid = ['type' => 'LineString', 'coordinates' => [[0, 0], [1, 1]]];
    $invalid = ['type' => 'LineString', 'coordinates' => [[0, 0]]];

    expect(GeoJsonGeometryValidator::isProcessable($valid))->toBeTrue()
        ->and(GeoJsonGeometryValidator::isProcessable($invalid))->toBeFalse();
});

test('a point with coordinates is processable', function () {
    expect(GeoJsonGeometryValidator::isProcessable(['type' => 'Point', 'coordinates' => [5.69, 50.85]]))->toBeTrue();
});

test('malformed geometry objects are not processable', function (array $geometry) {
    expect(GeoJsonGeometryValidator::isProcessable($geometry))->toBeFalse();
})->with([
    'missing coordinates' => [['type' => 'Polygon']],
    'missing type' => [['coordinates' => [[[0, 0], [0, 1], [1, 1], [0, 0]]]]],
    'non-array coordinates' => [['type' => 'Polygon', 'coordinates' => 'nope']],
]);
