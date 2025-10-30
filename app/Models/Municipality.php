<?php

namespace App\Models;

use App\Casts\AsGeoJson;
use App\Models\Contracts\HasGeometry;
use App\Models\Users\MunicipalityAdminUser;
use App\Models\Users\MunicipalityUser;
use App\Models\Users\ReviewerMunicipalityAdminUser;
use App\Models\Users\ReviewerUser;
use App\Observers\MunicipalityObserver;
use Brick\Geo\Geometry;
use Database\Factories\MunicipalityFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(MunicipalityObserver::class)]
class Municipality extends Model implements HasGeometry
{
    /** @use HasFactory<MunicipalityFactory> */
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

    public function getGeometry($field = 'geometry'): ?Geometry
    {
        return $this->getAttribute($field);
    }

    protected function casts(): array
    {
        return [
            'geometry' => AsGeoJson::class,
        ];
    }

    public function reviewerUsers()
    {
        return $this->belongsToMany(ReviewerUser::class, 'municipality_user');
    }

    public function municipalityAdminUsers()
    {
        return $this->belongsToMany(MunicipalityAdminUser::class, 'municipality_user');
    }

    public function reviewerMunicipalityAdminUsers()
    {
        return $this->belongsToMany(ReviewerMunicipalityAdminUser::class, 'municipality_user');
    }

    public function municipalityUsers()
    {
        return $this->belongsToMany(MunicipalityUser::class, 'municipality_user');
    }

    public function allReviewerUsers(): BelongsToMany
    {
        return $this->belongsToMany(MunicipalityUser::class, 'municipality_user');
    }

    public function allAdminUsers()
    {
        return $this->municipalityUsers()->admins();
    }

    public function variables()
    {
        return $this->hasMany(MunicipalityVariable::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function zaaktypen(): HasMany
    {
        return $this->hasMany(Zaaktype::class);
    }

    public function advisories()
    {
        return $this->belongsToMany(Advisory::class);
    }
}
