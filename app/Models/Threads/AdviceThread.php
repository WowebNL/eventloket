<?php

namespace App\Models\Threads;

use App\Enums\AdviceStatus;
use App\Enums\ThreadType;
use App\Models\Advisory;
use App\Models\Thread;
use App\Models\Zaak;
use App\Observers\AdviceThreadObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ThreadType $type
 * @property Advisory $advisory
 * @property Zaak $zaak
 */
#[ObservedBy(AdviceThreadObserver::class)]
class AdviceThread extends Thread
{
    public function getFillable()
    {
        return array_merge(parent::getFillable(), [
            'advisory_id',
            'advice_status',
            'advice_due_at',
        ]);
    }

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'advice_status' => AdviceStatus::class,
            'advice_due_at' => 'datetime',
        ]);
    }

    public function advisory(): BelongsTo
    {
        return $this->belongsTo(Advisory::class);
    }
}
