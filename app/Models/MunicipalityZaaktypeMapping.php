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
     * the logical identificatie. Used by call sites that operate on an existing
     * zaak/zaaktype and do not carry the role explicitly.
     */
    public static function forZaaktype(?Zaaktype $zaaktype): ?self
    {
        if (! $zaaktype || $zaaktype->municipality_id === null || ! $zaaktype->identificatie) {
            return null;
        }

        return static::query()
            ->where('municipality_id', $zaaktype->municipality_id)
            ->where('zaaktype_identificatie', $zaaktype->identificatie)
            ->first();
    }
}
