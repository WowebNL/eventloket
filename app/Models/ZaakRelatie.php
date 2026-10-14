<?php

namespace App\Models;

use App\Enums\ZaakRelatieType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * A typed, directed relation between two zaken. The direction is fixed
 * per type (see ZaakRelatieType): `zaak_id` is the subject that performs
 * the type on `gerelateerde_zaak_id`. Code outside the datamodel should
 * talk to the named helpers on `Zaak` (vervangtVooraankondiging(),
 * opgevolgdDoor()) instead of querying this model directly.
 *
 * @property ZaakRelatieType $type
 */
class ZaakRelatie extends Model
{
    use HasFactory;

    protected $table = 'zaak_relaties';

    protected $fillable = [
        'zaak_id',
        'gerelateerde_zaak_id',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'type' => ZaakRelatieType::class,
        ];
    }

    protected static function booted(): void
    {
        // Mirrors the database CHECK constraint, but fails with a clear
        // message before a query is attempted.
        static::saving(function (ZaakRelatie $relatie): void {
            if ($relatie->zaak_id === $relatie->gerelateerde_zaak_id) {
                throw new InvalidArgumentException('Een zaak kan geen relatie met zichzelf hebben.');
            }
        });
    }

    /** @return BelongsTo<Zaak, $this> */
    public function zaak(): BelongsTo
    {
        return $this->belongsTo(Zaak::class, 'zaak_id');
    }

    /** @return BelongsTo<Zaak, $this> */
    public function gerelateerdeZaak(): BelongsTo
    {
        return $this->belongsTo(Zaak::class, 'gerelateerde_zaak_id');
    }
}
