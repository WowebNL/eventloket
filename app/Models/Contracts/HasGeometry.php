<?php

namespace App\Models\Contracts;

use Brick\Geo\Geometry;

interface HasGeometry
{
    public function getGeometry($field = 'geometry'): ?Geometry;
}
