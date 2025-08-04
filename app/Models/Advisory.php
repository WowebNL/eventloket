<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Advisory extends Model
{
    /** @use HasFactory<\Database\Factories\AdvisoryFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function municipalities(): BelongsToMany
    {
        return $this->belongsToMany(Municipality::class);
    }
}
