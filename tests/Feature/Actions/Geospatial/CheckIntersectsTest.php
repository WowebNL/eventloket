<?php

use App\Actions\Geospatial\CheckIntersects;
use App\Models\Municipality;
use Brick\Geo\Polygon;

beforeEach(function () {
    $this->intersectsChecker = new CheckIntersects;
    Municipality::factory()->create();
});

test('Geometry intersects with geometry on the model', function () {
    $geometry = Polygon::fromText('POLYGON((0.5 0.5, 0.5 1.5, 1.5 1.5, 1.5 0.5, 0.5 0.5))', 4326);
    $result = $this->intersectsChecker->checkIntersectsWithModels($geometry);

    expect(count($result))->toBe(1);
});

test('Geometry does not intersect with geometry on the model', function () {
    $geometry = Polygon::fromText('POLYGON((2 2, 2 3, 3 3, 3 2, 2 2))', 4326);
    $result = $this->intersectsChecker->checkIntersectsWithModels($geometry);

    expect(count($result))->toBe(0);
});
