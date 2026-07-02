<?php

namespace App\Models;

use App\Casts\AsGeoJson;
use App\Enums\MunicipalityVariableType;
use App\Enums\Role;
use App\Enums\ZaaktypeRole;
use App\EventForm\Submit\ResolveZaaktype;
use App\Models\Contracts\HasGeometry;
use App\Models\Users\CoordinatorUser;
use App\Models\Users\MunicipalityAdminUser;
use App\Models\Users\MunicipalityUser;
use App\Models\Users\ReviewerMunicipalityAdminUser;
use App\Models\Users\ReviewerUser;
use App\Observers\MunicipalityObserver;
use App\Services\Zgw\ZgwConnectionResolver;
use Brick\Geo\Geometry;
use Database\Factories\MunicipalityFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function coordinatorUsers(): BelongsToMany
    {
        return $this->belongsToMany(CoordinatorUser::class, 'municipality_user');
    }

    public function allCoordinatorUsers(): BelongsToMany
    {
        return $this->municipalityUsers()->coordinators();
    }

    public function municipalityAdminUsers()
    {
        return $this->belongsToMany(MunicipalityAdminUser::class, 'municipality_user');
    }

    /**
     * Gemeentelijk beheerders and koppelingbeheerders together, used by the admin
     * panel's "Gemeentelijke beheerders" tab so both roles are managed in one place.
     *
     * @return BelongsToMany<MunicipalityUser, $this>
     */
    public function municipalityBeheerderUsers(): BelongsToMany
    {
        return $this->belongsToMany(MunicipalityUser::class, 'municipality_user')
            ->whereIn('role', [Role::MunicipalityAdmin->value, Role::KoppelingBeheerder->value]);
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

    /**
     * The ZGW connection name to use for calls about this municipality.
     */
    public function zgwConnectionName(): string
    {
        return app(ZgwConnectionResolver::class)->for($this);
    }

    /**
     * The municipality's own ZGW connection, when configured. Its absence means
     * the municipality falls back to the global "main" connection.
     *
     * @return HasOne<MunicipalityZgwConnection, $this>
     */
    public function zgwConnection(): HasOne
    {
        return $this->hasOne(MunicipalityZgwConnection::class);
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

    /**
     * The active doorkomst zaaktype to use when creating route-passage deelzaken
     * for this municipality, or null when none is configured.
     *
     * Resolution order, mirroring {@see ResolveZaaktype}:
     *   1. the per-municipality blueprint mapping for the Doorkomst role;
     *   2. the explicit role column on a zaaktype of this municipality;
     *   3. the legacy doorkomst_zaaktype_id FK.
     *
     * Own-instance municipalities are skipped by SyncZaaktypen's name-link (which
     * sets doorkomst_zaaktype_id), so the blueprint/role steps are what give them
     * a doorkomst zaaktype.
     */
    public function resolveDoorkomstZaaktype(): ?Zaaktype
    {
        $mapping = MunicipalityZaaktypeMapping::forMunicipalityRole($this, ZaaktypeRole::Doorkomst);

        if ($mapping && $mapping->zaaktype_identificatie) {
            $byMapping = Zaaktype::query()
                ->where('municipality_id', $this->id)
                ->where('is_active', true)
                ->where('identificatie', $mapping->zaaktype_identificatie)
                ->first();

            if ($byMapping) {
                return $byMapping;
            }
        }

        $byRole = Zaaktype::query()
            ->where('municipality_id', $this->id)
            ->where('is_active', true)
            ->where('role', ZaaktypeRole::Doorkomst->value)
            ->first();

        if ($byRole) {
            return $byRole;
        }

        /** @var Zaaktype|null $legacy */
        $legacy = $this->doorkomstZaaktype;

        return $legacy && $legacy->is_active ? $legacy : null;
    }

    /** @return HasMany<MunicipalityZaaktypeMapping, $this> */
    public function zaaktypeMappings(): HasMany
    {
        return $this->hasMany(MunicipalityZaaktypeMapping::class);
    }

    public function advisories()
    {
        return $this->belongsToMany(Advisory::class);
    }
}
