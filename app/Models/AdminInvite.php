<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\AdminInviteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AdminInvite extends Model
{
    /** @use HasFactory<AdminInviteFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'role',
        'token',
    ];

    protected $hidden = [
        'token',
    ];

    public function municipalities(): BelongsToMany
    {
        return $this->belongsToMany(Municipality::class, 'admin_invite_municipality');
    }

    protected function casts(): array
    {
        return [
            'role' => Role::class,
        ];
    }
}
