<?php

namespace App\Models\Traits;

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
}
