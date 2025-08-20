<?php

namespace App\Models;

use App\Enums\OrganisationType;
use Database\Factories\OrganisationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Organisation extends Model
{
    /** @use HasFactory<OrganisationFactory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'coc_number',
        'address',
        'email',
        'phone',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role');
    }

    protected function casts(): array
    {
        return [
            'type' => OrganisationType::class,
        ];
    }
}
