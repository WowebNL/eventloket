<?php

namespace App\Models\Archiving;

use App\Models\Municipality;
use Database\Factories\Archiving\ZaakDestructionLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The record that Eventloket destroyed its own data about one zaak.
 *
 * Written the moment the data is gone, and rolled up into an "Eventloket data"
 * destruction report by archiving:report-eventloket-destructions the next
 * night. Every field is a snapshot: the zaak no longer exists on either side.
 *
 * @property Carbon $destroyed_at
 * @property ?Carbon $reported_at
 * @property ?string $zaaknummer
 * @property ?string $zaaktype_naam
 * @property string $zgw_connection
 * @property string $zgw_zaak_url
 * @property-read ?Municipality $municipality
 * @property-read ?DestructionReport $destructionReport
 */
class ZaakDestructionLog extends Model
{
    /** @use HasFactory<ZaakDestructionLogFactory> */
    use HasFactory;

    protected $fillable = [
        'municipality_id',
        'zgw_connection',
        'zgw_zaak_url',
        'zaaknummer',
        'zaaktype_naam',
        'destroyed_at',
        'destruction_report_id',
        'reported_at',
    ];

    protected function casts(): array
    {
        return [
            'destroyed_at' => 'datetime',
            'reported_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Municipality, $this> */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /** @return BelongsTo<DestructionReport, $this> */
    public function destructionReport(): BelongsTo
    {
        return $this->belongsTo(DestructionReport::class);
    }

    /**
     * Rows that still have to be reported.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeUnreported(Builder $query): void
    {
        $query->whereNull('reported_at');
    }

    /**
     * The entry as it appears in the report's permanent items snapshot.
     *
     * @return array<string, mixed>
     */
    public function toReportEntry(): array
    {
        return [
            'zaaknummer' => $this->zaaknummer,
            'zaaktype' => $this->zaaktype_naam,
            'zgw_zaak_url' => $this->zgw_zaak_url,
            'zgw_connection' => $this->zgw_connection,
            'destroyed_at' => $this->destroyed_at->toIso8601String(),
        ];
    }
}
