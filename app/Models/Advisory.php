<?php

namespace App\Models;

use App\Models\Users\AdvisorUser;
use Database\Factories\AdvisoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Advisory extends Model
{
    /** @use HasFactory<AdvisoryFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(AdvisorUser::class, 'advisory_user');
    }

    public function municipalities(): BelongsToMany
    {
        return $this->belongsToMany(Municipality::class);
    }
}
