<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Zaak extends Model
{
    /** @use HasFactory<\Database\Factories\ZaakFactory> */
    use HasFactory, HasUuids;

    protected $table = 'zaken';

    protected $fillable = [
        'id',
        'public_id',
        'zaaktype_id',
        'organisation_id',
        'name',
    ];

    public function zaaktype()
    {
        return $this->belongsTo(Zaaktype::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
