<?php

namespace App\Models;

use App\Enums\OrganisationType;
use App\Models\Traits\HasUuid;
use App\Models\Users\OrganiserUser;
use App\Services\LocatieserverService;
use Database\Factories\OrganisationFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Organisation extends Model
{
    /** @use HasFactory<OrganisationFactory> */
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'type',
        'name',
        'coc_number',
        'address',
        'bag_id',
        'email',
        'phone',
    ];

    protected $appends = [
        'bag_address',
    ];

    /** @return Attribute<\App\ValueObjects\Pdok\BagObject|null, void> */
    protected function bagAddress(): Attribute
    {
        return Attribute::make(
            get: function ($value, array $attributes) {
                if (! isset($attributes['bag_id']) || ! $attributes['bag_id']) {
                    return null;
                }

                return Cache::rememberForever("organisation.{$this->id}.{$attributes['bag_id']}", function () use ($attributes) {
                    return (new LocatieserverService)->getBagObjectById($attributes['bag_id']);
                });
            }
        );
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(OrganiserUser::class, 'organisation_user')->withPivot('role');
    }

    public function formSubmissionSessions(): HasMany
    {
        return $this->hasMany(FormsubmissionSession::class);
    }

    protected function casts(): array
    {
        return [
            'type' => OrganisationType::class,
        ];
    }
}
