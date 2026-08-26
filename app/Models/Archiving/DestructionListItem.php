<?php

namespace App\Models\Archiving;

use App\Enums\DestructionItemStatus;
use App\Models\Zaak;
use Database\Factories\Archiving\DestructionListItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One zaak on a destruction list. All zaak fields are stored as a snapshot,
 * because the zaak itself no longer exists after destruction.
 *
 * @property DestructionItemStatus $status
 * @property ?Carbon $archiefactiedatum
 * @property ?Carbon $destroyed_at
 * @property-read DestructionList $destructionList
 * @property-read ?Zaak $zaak
 */
class DestructionListItem extends Model
{
    /** @use HasFactory<DestructionListItemFactory> */
    use HasFactory;

    protected $fillable = [
        'destruction_list_id',
        'zaak_id',
        'zgw_zaak_url',
        'zaaknummer',
        'zaaktype_naam',
        'naam_evenement',
        'archiefnominatie',
        'archiefactiedatum',
        'archiefstatus',
        'selectielijstklasse',
        'selectielijst_categorie',
        'bewaartermijn',
        'brondatum_archiefprocedure',
        'status',
        'failure_reason',
        'destroyed_at',
    ];

    protected function casts(): array
    {
        return [
            'archiefactiedatum' => 'date',
            'status' => DestructionItemStatus::class,
            'destroyed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<DestructionList, $this> */
    public function destructionList(): BelongsTo
    {
        return $this->belongsTo(DestructionList::class);
    }

    /** @return BelongsTo<Zaak, $this> */
    public function zaak(): BelongsTo
    {
        return $this->belongsTo(Zaak::class);
    }

    /**
     * The snapshot of this item as included in the destruction report.
     */
    public function toReportEntry(): array
    {
        return [
            'zaaknummer' => $this->zaaknummer,
            'zaaktype' => $this->zaaktype_naam,
            'naam_evenement' => $this->naam_evenement,
            'zgw_zaak_url' => $this->zgw_zaak_url,
            'archiefnominatie' => $this->archiefnominatie,
            'archiefactiedatum' => $this->archiefactiedatum?->toDateString(),
            'selectielijstklasse' => $this->selectielijstklasse,
            'selectielijst_categorie' => $this->selectielijst_categorie,
            'bewaartermijn' => $this->bewaartermijn,
            'brondatum_archiefprocedure' => $this->brondatum_archiefprocedure,
            'status' => $this->status->value,
            'failure_reason' => $this->failure_reason,
            'destroyed_at' => $this->destroyed_at?->toIso8601String(),
        ];
    }
}
