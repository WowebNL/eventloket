<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zaaktype extends Model
{
    use HasUuids;

    protected $table = 'zaaktypen';

    protected $fillable = [
        'name',
        'oz_url',
        'is_active',
    ];

    public function zaken(): HasMany
    {
        return $this->hasMany(Zaak::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }
}
