<?php

namespace App\Models\Traits;

trait Expirable
{
    /**
     * Scope to get expired invites
     */
    protected function scopeExpired($query)
    {
        return $query->where('created_at', '<=', now()->subDays(config('invites.expiration_days')));
    }
}
