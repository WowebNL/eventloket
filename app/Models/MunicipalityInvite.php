<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Traits\Expirable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MunicipalityInvite extends Model
{
    /** @use HasFactory<\Database\Factories\MunicipalityInviteFactory> */
    use Expirable, HasFactory;

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
        return $this->belongsToMany(Municipality::class);
    }

    protected function casts(): array
    {
        return [
            'role' => Role::class,
        ];
    }
}
