<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Zaaktype extends Model
{
    /** @use HasFactory<\Database\Factories\ZaaktypeFactory> */
    use HasFactory, HasUuids;

    protected $table = 'zaaktypen';

    protected $fillable = [
        'id',
        'public_id',
        'name',
    ];

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }
}
