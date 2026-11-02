<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ZaaktypeRole;
use App\Observers\MunicipalityZaaktypeMappingObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-municipality blueprint linking a logical {@see ZaaktypeRole} to a
 * concrete ZGW zaaktype and its eigenschap/flow-blocker selectors.
 *
 * A missing row, or a null column, means "fall back to the original
 * heuristic", so an empty table reproduces the pre-blueprint behaviour.
 *
 * @property int $municipality_id
 * @property ZaaktypeRole $role
 * @property string|null $zaaktype_identificatie
 * @property bool|null $triggers_route_check
 * @property array<int, string>|null $hidden_resultaat_types
 * @property array<string, string>|null $eigenschap_map
 * @property string|null $initial_statustype
 * @property string|null $eind_statustype
 * @property string|null $initiator_roltype
 * @property string|null $ingetrokken_resultaattype
 * @property string|null $bijlage_informatieobjecttype
 * @property string|null $aanvraag_informatieobjecttype
 */
#[ObservedBy(MunicipalityZaaktypeMappingObserver::class)]
class MunicipalityZaaktypeMapping extends Model
{
    protected $fillable = [
        'municipality_id',
        'role',
        'zaaktype_identificatie',
        'triggers_route_check',
        'hidden_resultaat_types',
        'eigenschap_map',
        'initial_statustype',
        'eind_statustype',
        'initiator_roltype',
        'ingetrokken_resultaattype',
        'bijlage_informatieobjecttype',
        'aanvraag_informatieobjecttype',
    ];

    protected function casts(): array
    {
        return [
            'role' => ZaaktypeRole::class,
            'triggers_route_check' => 'boolean',
            'hidden_resultaat_types' => 'array',
            'eigenschap_map' => 'array',
        ];
    }

    /** @return BelongsTo<Municipality, $this> */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /**
     * The blueprint for a specific municipality + role, or null when none is
     * configured (callers then fall back to the heuristic).
     */
    public static function forMunicipalityRole(Municipality $municipality, ZaaktypeRole $role): ?self
    {
        return static::query()
            ->where('municipality_id', $municipality->id)
            ->where('role', $role->value)
            ->first();
    }

    /**
     * The blueprint that owns a given zaaktype, matched by the municipality and
     * the logical identificatie. Used by call sites that operate on a zaaktype
     * without knowing the role.
     *
     * A municipality may map several roles to the same zaaktype, and then this
     * returns the first of them. Whenever a zaak is at hand, use
     * {@see self::forZaak()} instead, which picks the koppeling of the zaak's
     * own role.
     */
    public static function forZaaktype(?Zaaktype $zaaktype): ?self
    {
        if (! $zaaktype || $zaaktype->municipality_id === null || ! $zaaktype->identificatie) {
            return null;
        }

        return static::query()
            ->where('municipality_id', $zaaktype->municipality_id)
            ->where('zaaktype_identificatie', $zaaktype->identificatie)
            ->orderBy('id')
            ->first();
    }

    /**
     * The blueprint for a zaaktype in a given role: the koppeling of that role,
     * provided it points at this very zaaktype.
     *
     * The identificatie check matters when a zaak falls back to a zaaktype of
     * another catalogus: the role's koppeling then names a different zaaktype
     * and must not be applied. In that case, and when no role is known, this
     * answers what {@see self::forZaaktype()} answers.
     */
    public static function forZaaktypeInRole(?Zaaktype $zaaktype, ?ZaaktypeRole $role): ?self
    {
        if (! $zaaktype || $zaaktype->municipality_id === null || ! $zaaktype->identificatie) {
            return null;
        }

        if ($role !== null) {
            $mapping = static::query()
                ->where('municipality_id', $zaaktype->municipality_id)
                ->where('role', $role->value)
                ->where('zaaktype_identificatie', $zaaktype->identificatie)
                ->first();

            if ($mapping !== null) {
                return $mapping;
            }
        }

        return static::forZaaktype($zaaktype);
    }

    /**
     * The blueprint that applies to a zaak: the koppeling of its own role on
     * its zaaktype. A zaak without a stored role falls back to the zaaktype.
     */
    public static function forZaak(?Zaak $zaak): ?self
    {
        if (! $zaak) {
            return null;
        }

        return static::forZaaktypeInRole($zaak->zaaktype, $zaak->role);
    }
}
