<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvisoryInvite extends Model
{
    /** @use HasFactory<\Database\Factories\AdvisoryInviteFactory> */
    use HasFactory;

    protected $fillable = [
        'advisory_id',
        'email',
        'token',
    ];

    protected $hidden = [
        'token',
    ];

    public function advisory(): BelongsTo
    {
        return $this->belongsTo(Advisory::class);
    }
}
