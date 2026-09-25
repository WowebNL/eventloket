<?php

namespace App\Models\Archiving;

use App\Models\Municipality;
use Database\Factories\Archiving\DestructionReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    /**
     * Whether the rendered PDF can still be read. It lives on a configurable
     * disk, so it can go missing while the report itself survives.
     */
    public function hasPdf(): bool
    {
        return $this->pdf_path !== null
            && Storage::disk(config('archiving.report_disk'))->exists($this->pdf_path);
    }

    /**
     * The number this report is filed under, for example VL-GM0935-2026-001.
     * The sequence continues after the highest number already issued, so a
     * deleted row can never hand out a number that is in use.
     */
    public static function nextBatchNumber(Municipality $municipality): string
    {
        // The BRK code is the public identifier of a municipality; its
        // internal id does not belong in a permanent legal document.
        $code = $municipality->brk_identification ?: Str::upper(Str::slug($municipality->name));
        $prefix = sprintf('VL-%s-%d-', $code, now()->year);

        $sequence = static::where('batch_number', 'like', $prefix.'%')
            ->pluck('batch_number')
            ->map(fn (string $batchNumber): int => (int) Str::afterLast($batchNumber, '-'))
            ->max() ?? 0;

        return $prefix.sprintf('%03d', $sequence + 1);
    }
}
