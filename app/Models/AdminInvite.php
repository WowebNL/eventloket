<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminInvite extends Model
{
    /** @use HasFactory<\Database\Factories\AdminInviteFactory> */
    use HasFactory;

    protected $fillable = [
        'municipality_id',
        'name',
        'email',
        'role',
        'token',
    ];

    protected $hidden = [
        'token',
    ];

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    protected function casts(): array
    {
        return [
            'role' => Role::class,
        ];
    }
}
