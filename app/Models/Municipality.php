<?php

namespace App\Models;

use App\Casts\AsGeoJson;
use App\Models\Contracts\HasGeometry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Municipality extends Model implements HasGeometry
{
    /** @use HasFactory<\Database\Factories\MunicipalityFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'brk_identification',
        'brk_uuid',
        'geometry',
    ];

    protected $hidden = [
        'geometry',
    ];

    public function getGeometry($field = 'geometry'): ?\Brick\Geo\Geometry
    {
        return $this->getAttribute($field);
    }

    protected function casts(): array
    {
        return [
            'geometry' => AsGeoJson::class,
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function advisories()
    {
        return $this->belongsToMany(Advisory::class);
    }
}
