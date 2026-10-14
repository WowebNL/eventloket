<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Stores email addresses in lowercase, the way User does.
 *
 * PostgreSQL compares strings case sensitively, so an invite that kept the
 * address as the inviter typed it could never be matched against the account
 * it belongs to.
 */
trait NormalisesEmail
{
    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = $value === null ? null : strtolower($value);
    }

    /**
     * Match an address the way it is stored, whatever case it was typed in.
     *
     * @param  Builder<static>  $query
     */
    public function scopeWhereEmail(Builder $query, string $email): void
    {
        $query->where('email', strtolower($email));
    }
}
