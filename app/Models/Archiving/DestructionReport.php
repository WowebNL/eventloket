<?php

namespace App\Models\Archiving;

use App\Models\Municipality;
use Database\Factories\Archiving\DestructionReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The legally required proof of destruction. Immutable and kept permanently.
 *
 * @property-read Municipality $municipality
 */
class DestructionReport extends Model
{
    /** @use HasFactory<DestructionReportFactory> */
    use HasFactory;

    protected $fillable = [
        'municipality_id',
        'destruction_list_id',
        'batch_number',
        'coordinator_name',
        'coordinator_function',
        'coordinator_user_id',
        'destruction_method',
        'destruction_date',
        'items',
        'pdf_path',
        'total_count',
        'deleted_count',
        'failed_count',
        'skipped_count',
    ];

    protected function casts(): array
    {
        return [
            'destruction_date' => 'datetime',
            'items' => 'array',
        ];
    }

    /** @return BelongsTo<Municipality, $this> */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /** @return BelongsTo<DestructionList, $this> */
    public function destructionList(): BelongsTo
    {
        return $this->belongsTo(DestructionList::class);
    }

    public static function nextBatchNumber(Municipality $municipality): string
    {
        $year = now()->year;

        $sequence = static::where('municipality_id', $municipality->id)
            ->whereYear('created_at', $year)
            ->count() + 1;

        return sprintf('VL-%d-%d-%03d', $municipality->id, $year, $sequence);
    }
}
