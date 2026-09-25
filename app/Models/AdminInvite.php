<?php

namespace App\Models;

use App\Models\Traits\Expirable;
use App\Models\Traits\NormalisesEmail;
use Database\Factories\AdminInviteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminInvite extends Model
{
    /** @use HasFactory<AdminInviteFactory> */
    use Expirable, HasFactory, NormalisesEmail;

    protected $fillable = [
        'name',
        'email',
        'token',
    ];

    protected $hidden = [
        'token',
    ];
}
