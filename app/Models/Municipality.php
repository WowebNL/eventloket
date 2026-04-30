<?php

namespace App\Models;

use App\Casts\AsGeoJson;
use App\Enums\MunicipalityVariableType;
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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'use_new_report_questions',
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
            'use_new_report_questions' => 'boolean',
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
        return $this->municipalityUsers()->reviewers();
    }

    public function allAdminUsers()
    {
        return $this->municipalityUsers()->admins();
    }

    public function locations()
    {
        return $this->hasMany(Location::class);
    }

    public function variables()
    {
        return $this->hasMany(MunicipalityVariable::class);
    }

    public function defaultAdviceQuestions()
    {
        return $this->hasMany(DefaultAdviceQuestion::class);
    }

    /** @return HasMany<MunicipalityVariable, $this> */
    public function oldReportQuestions(): HasMany
    {
        return $this->variables()->where('type', MunicipalityVariableType::ReportQuestion);
    }

    /** @return HasMany<ReportQuestion, $this> */
    public function reportQuestions(): HasMany
    {
        return $this->hasMany(ReportQuestion::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function zaaktypen(): HasMany
    {
        return $this->hasMany(Zaaktype::class);
    }

    public function doorkomstZaaktype(): BelongsTo
    {
        return $this->belongsTo(Zaaktype::class, 'doorkomst_zaaktype_id');
    }

    public function advisories()
    {
        return $this->belongsToMany(Advisory::class);
    }
}
