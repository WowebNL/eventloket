<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewerInvite extends Model
{
    /** @use HasFactory<\Database\Factories\ReviewerInviteFactory> */
    use HasFactory;

    protected $fillable = [
        'municipality_id',
        'email',
        'token',
    ];

    protected $hidden = [
        'token',
    ];

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }
}
