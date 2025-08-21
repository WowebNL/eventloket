<?php

namespace App\Models;

use Database\Factories\AdminInviteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminInvite extends Model
{
    /** @use HasFactory<AdminInviteFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'token',
    ];

    protected $hidden = [
        'token',
    ];
}
