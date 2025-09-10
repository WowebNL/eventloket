<?php

namespace App\Models\Threads;

use App\Enums\AdviceStatus;
use App\Models\Advisory;
use App\Models\Thread;

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

    public function advisory()
    {
        return $this->belongsTo(Advisory::class);
    }
}
