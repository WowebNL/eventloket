<?php

namespace App\Models;

use App\Models\Scopes\ZaakEventScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;

#[ScopedBy(ZaakEventScope::class)]
class Event extends Zaak
{
    protected $fillable = [];

    protected static function booted(): void
    {
        static::addGlobalScope(new ZaakEventScope);
    }
}
