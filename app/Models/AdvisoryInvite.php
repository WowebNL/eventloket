<?php

namespace App\Models;

use App\Models\Traits\Expirable;
use Database\Factories\AdvisoryInviteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvisoryInvite extends Model
{
    /** @use HasFactory<AdvisoryInviteFactory> */
    use Expirable, HasFactory;

    protected $fillable = [
        'advisory_id',
        'name',
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
