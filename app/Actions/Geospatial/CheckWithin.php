<?php

namespace App\Actions\Geospatial;

use App\Models\Contracts\HasGeometry;
use App\Models\Municipality;
use Brick\Geo\Engine\GeometryEngine;
use Brick\Geo\Engine\PdoEngine;
use Brick\Geo\Geometry;
use Illuminate\Support\Facades\DB;

class CheckWithin
{
    private ?Geometry $fullGeometry;

    public function __construct(private ?GeometryEngine $geometryEngine = null, string $modelClass = Municipality::class)
    {
        $this->geometryEngine = $this->geometryEngine ?? new PdoEngine(DB::connection()->getPdo());
        $fullGeometry = null;
        $modelClass::whereNotNull('geometry')->each(function (HasGeometry $item) use (&$fullGeometry) {
            $fullGeometry = $fullGeometry ? $this->geometryEngine->union($fullGeometry, $item->getGeometry()) : $item->getGeometry();
        });
        $this->fullGeometry = $fullGeometry;
    }

    public function checkWithinAllGeometriesFromModels(Geometry $geometry): bool
    {
        if ($this->fullGeometry) {
            return $this->geometryEngine->within($geometry, $this->fullGeometry);
        }

        return false;
    }
}
