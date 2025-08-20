<?php

namespace App\Actions\Geospatial;

use App\Models\Contracts\HasGeometry;
use App\Models\Municipality;
use Brick\Geo\Engine\GeometryEngine;
use Brick\Geo\Engine\PdoEngine;
use Brick\Geo\Geometry;
use Illuminate\Support\Facades\DB;

class CheckIntersects
{
    public function __construct(private ?GeometryEngine $geometryEngine = null)
    {
        $this->geometryEngine = $this->geometryEngine ?? new PdoEngine(DB::connection()->getPdo());
    }

    public function checkIntersectsWithModels(Geometry $geometry, string $modelClass = Municipality::class): array
    {
        return $modelClass::whereNotNull('geometry')->get()->filter(function (HasGeometry $model) use ($geometry) {
            return $this->intersectsWithGeometryOnModel($model, $geometry);
        })->all();
    }

    public function intersectsWithGeometryOnModel(HasGeometry $model, Geometry $geometry): bool
    {
        return $this->geometryEngine->intersects($geometry, $model->getGeometry());
    }
}
