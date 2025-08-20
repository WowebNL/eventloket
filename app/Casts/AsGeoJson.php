<?php

namespace App\Casts;

use Brick\Geo\Io\GeoJsonReader;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class AsGeoJson implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @return \Brick\Geo\Geometry|\Brick\Geo\Io\GeoJson\Feature|\Brick\Geo\Io\GeoJson\FeatureCollection|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return ! empty($value) && json_validate($value) ? (new GeoJsonReader)->read($value) : null;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return is_array($value) ? json_encode($value) : $value;
    }
}
