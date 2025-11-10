<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends Model
{
    /** @use HasFactory<\Database\Factories\LocationFactory> */
    use HasFactory;

    protected $fillable = [
        'municipality_id',
        'name',
        'postal_code',
        'house_number',
        'house_letter',
        'house_number_addition',
        'street_name',
        'city_name',
        'active',
        'geometry',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'geometry' => 'json',
        ];
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }
}
