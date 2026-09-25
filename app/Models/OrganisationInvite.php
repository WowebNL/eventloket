<?php

namespace App\Models;

use App\Models\Traits\Expirable;
use App\Models\Traits\NormalisesEmail;
use Database\Factories\OrganisationInviteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganisationInvite extends Model
{
    /** @use HasFactory<OrganisationInviteFactory> */
    use Expirable, HasFactory, NormalisesEmail;

    protected $fillable = [
        'organisation_id',
        'name',
        'email',
        'role',
        'token',
    ];

    protected $hidden = [
        'token',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
