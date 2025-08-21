<?php

namespace App\Actions\Geospatial;

use App\Models\Contracts\HasGeometry;
use App\Models\Municipality;
use Brick\Geo\Engine\GeometryEngine;
use Brick\Geo\Engine\PdoEngine;
use Brick\Geo\Geometry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CheckIntersects
{
    private $items;

    public function __construct(private ?GeometryEngine $geometryEngine = null, string $modelClass = Municipality::class)
    {
        $this->geometryEngine = $this->geometryEngine ?? new PdoEngine(DB::connection()->getPdo());
        $this->items = $modelClass::whereNotNull('geometry')->get();
    }

    public function checkIntersectsWithModels(Geometry $geometry): Collection
    {
        return $this->items->filter(function (HasGeometry $model) use ($geometry) {
            return $this->intersectsWithGeometryOnModel($model, $geometry);
        });
    }

    public function intersectsWithGeometryOnModel(HasGeometry $model, Geometry $geometry): bool
    {
        return $this->geometryEngine->intersects($geometry, $model->getGeometry());
    }
}
