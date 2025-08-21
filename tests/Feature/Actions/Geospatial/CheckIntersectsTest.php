<?php

use App\Actions\Geospatial\CheckIntersects;
use App\Models\Municipality;
use Brick\Geo\Polygon;

beforeEach(function () {
    Municipality::factory()->create();
    $this->intersectsChecker = new CheckIntersects;
});

test('Geometry intersects with geometry on the model', function () {
    $geometry = Polygon::fromText('POLYGON((0.5 0.5, 0.5 1.5, 1.5 1.5, 1.5 0.5, 0.5 0.5))', 4326);
    $result = $this->intersectsChecker->checkIntersectsWithModels($geometry);
    expect($result->count())->toBe(1);
});

test('Geometry does not intersect with geometry on the model', function () {
    $geometry = Polygon::fromText('POLYGON((2 2, 2 3, 3 3, 3 2, 2 2))', 4326);
    $result = $this->intersectsChecker->checkIntersectsWithModels($geometry);

    expect($result->count())->toBe(0);
});
