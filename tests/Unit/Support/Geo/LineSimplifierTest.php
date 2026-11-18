<?php

declare(strict_types=1);

use App\Support\Geo\LineSimplifier;

/**
 * A dense line that wanders: straight stretches the simplifier can thin out,
 * with corners it has to keep.
 *
 * @return list<array{0: float, 1: float}>
 */
function denseLine(int $positions): array
{
    $line = [];

    for ($index = 0; $index < $positions; $index++) {
        $line[] = [
            5.0 + $index * 0.0001,
            50.0 + sin($index / 40) * 0.02,
        ];
    }

    return $line;
}

test('a line inside the budget is returned untouched', function () {
    $line = denseLine(10);

    expect(LineSimplifier::simplify($line))->toBe($line);
});

test('a line over the budget is brought under it', function () {
    $line = denseLine(5000);

    $reduced = LineSimplifier::simplify($line);

    expect(count($line))->toBe(5000)
        ->and(count($reduced))->toBeLessThanOrEqual(LineSimplifier::MAX_POSITIONS)
        ->and(count($reduced))->toBeGreaterThan(2);
});

test('the first and the last position survive', function () {
    // The municipality check reads the start and the end of the line to work
    // out which municipality the route starts and ends in, so neither may be
    // thinned away.
    $line = denseLine(5000);

    $reduced = LineSimplifier::simplify($line);

    expect($reduced[0])->toBe($line[0])
        ->and($reduced[count($reduced) - 1])->toBe($line[4999]);
});

test('the reduced line keeps the course of the original', function () {
    $line = denseLine(5000);

    $reduced = LineSimplifier::simplify($line);

    // Every kept position is one of the original positions, in order.
    $indexInOriginal = 0;

    foreach ($reduced as $position) {
        while ($indexInOriginal < count($line) && $line[$indexInOriginal] !== $position) {
            $indexInOriginal++;
        }

        expect($indexInOriginal)->toBeLessThan(count($line));
    }
});

test('a line that resists thinning still fits the budget', function () {
    // A line doubling back on itself over and over: every position adds
    // detail, so the distance-based pass cannot drop enough of them.
    $line = [];

    for ($index = 0; $index < 4000; $index++) {
        $line[] = [5.0 + ($index % 2) * 0.5, 50.0 + $index * 0.0001];
    }

    $reduced = LineSimplifier::simplify($line);

    expect(count($reduced))->toBeLessThanOrEqual(LineSimplifier::MAX_POSITIONS)
        ->and($reduced[0])->toBe($line[0])
        ->and($reduced[count($reduced) - 1])->toBe($line[3999]);
});

test('a budget can be given per call', function () {
    $reduced = LineSimplifier::simplify(denseLine(5000), 50);

    expect(count($reduced))->toBeLessThanOrEqual(50);
});

test('a LineString geometry is reduced in place', function () {
    $geometry = ['type' => 'LineString', 'coordinates' => denseLine(5000)];

    $reduced = LineSimplifier::simplifyGeometry($geometry);

    expect($reduced['type'])->toBe('LineString')
        ->and(count($reduced['coordinates']))->toBeLessThanOrEqual(LineSimplifier::MAX_POSITIONS);
});

test('every line of a MultiLineString gets its own budget', function () {
    $geometry = [
        'type' => 'MultiLineString',
        'coordinates' => [denseLine(5000), denseLine(3000)],
    ];

    $reduced = LineSimplifier::simplifyGeometry($geometry);

    expect($reduced['coordinates'])->toHaveCount(2);

    foreach ($reduced['coordinates'] as $line) {
        expect(count($line))->toBeLessThanOrEqual(LineSimplifier::MAX_POSITIONS);
    }
});

test('a geometry that is not a line is left alone', function () {
    $geometry = [
        'type' => 'Polygon',
        'coordinates' => [[[0, 0], [1, 0], [1, 1], [0, 0]]],
    ];

    expect(LineSimplifier::simplifyGeometry($geometry))->toBe($geometry);
});
