<?php

namespace App\Models\Archiving;

use App\Enums\DestructionItemStatus;
use App\Enums\DestructionListStatus;
use App\Models\Municipality;
use App\Models\Users\MunicipalityUser;
use Database\Factories\Archiving\DestructionListFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property DestructionListStatus $status
 * @property-read Municipality $municipality
 */
class DestructionList extends Model
{
    /** @use HasFactory<DestructionListFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'municipality_id',
        'name',
        'status',
        'created_by_user_id',
        'review_feedback',
        'reviewed_by_user_id',
        'reviewed_at',
        'approved_at',
        'confirmed_at',
        'coordinator_name',
        'coordinator_function',
        'destruction_method',
        'destruction_report_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => DestructionListStatus::class,
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Municipality, $this> */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /** @return HasMany<DestructionListItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(DestructionListItem::class);
    }

    /** @return BelongsTo<MunicipalityUser, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(MunicipalityUser::class, 'created_by_user_id');
    }

    /** @return BelongsTo<MunicipalityUser, $this> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(MunicipalityUser::class, 'reviewed_by_user_id');
    }

    /** @return BelongsTo<DestructionReport, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(DestructionReport::class, 'destruction_report_id');
    }

    public function canTransitionTo(DestructionListStatus $status): bool
    {
        return in_array($status, $this->status->allowedTransitions());
    }

    public function transitionTo(DestructionListStatus $status, array $attributes = []): void
    {
        if (! $this->canTransitionTo($status)) {
            throw new \InvalidArgumentException(
                "Invalid destruction list status transition from [{$this->status->value}] to [{$status->value}]."
            );
        }

        $this->fill(array_merge($attributes, ['status' => $status]))->save();
    }

    public function hasFailedItems(): bool
    {
        return $this->items()->where('status', DestructionItemStatus::Failed)->exists();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty();
    }
}
