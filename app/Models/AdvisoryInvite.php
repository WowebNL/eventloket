<?php

namespace App\Models;

use App\Models\Traits\Expirable;
use App\Models\Traits\NormalisesEmail;
use Database\Factories\AdvisoryInviteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvisoryInvite extends Model
{
    /** @use HasFactory<AdvisoryInviteFactory> */
    use Expirable, HasFactory, NormalisesEmail;

    protected $fillable = [
        'advisory_id',
        'name',
        'email',
        'role',
        'token',
    ];

    protected $hidden = [
        'token',
    ];

    /**
     * @return BelongsTo<Advisory, $this>
     */
    public function advisory(): BelongsTo
    {
        return $this->belongsTo(Advisory::class);
    }
}
