<?php

use App\Actions\Geospatial\CheckIntersects;
use App\Models\Municipality;
use Brick\Geo\Polygon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
beforeEach(function () {
    // Fix for running tests in parallel with PostgreSQL and PostGIS
    // Check if we're using PostgreSQL and enable PostGIS extension if needed
    if (config('database.default') === 'pgsql') {
        try {
            \Illuminate\Support\Facades\DB::statement('CREATE EXTENSION IF NOT EXISTS postgis;');
        } catch (\Exception $e) {
            // Extension might already exist or there might be permission issues
            // We'll continue with the test as PostGIS should be available in the Docker container
        }
    }

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
