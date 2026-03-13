<?php

use App\Actions\Geospatial\SyncGeometry;
use App\Models\Municipality;
use Brick\Geo\Geometry;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        config('services.kadaster.base_url').'/bestuurlijkegebieden/ogc/v1/collections/gemeentegebied/items*' => Http::response([
            'features' => [[
                'properties' => [
                    'identificatie' => 'GM123',
                    'name' => 'Test Municipality',
                ],
                'geometry' => [
                    'type' => 'MultiPolygon',
                    'coordinates' => [
                        [
                            [
                                [0, 0],
                                [0, 1],
                                [1, 1],
                                [1, 0],
                                [0, 0],
                            ],
                        ],
                    ],
                ],
            ]],
        ]),
    ]);
    $this->municipality = Municipality::factory()->create([
        'brk_identification' => 'GM123',
        'geometry' => null,
    ]);
});

test('Muncipality geometry can be synced', function () {
    $model = Municipality::find($this->municipality->id);
    expect($model->geometry)->toBeNull();

    (new SyncGeometry($this->municipality))->execute();

    $model->refresh();
    expect($model->geometry)->toBeInstanceOf(Geometry::class);
});
