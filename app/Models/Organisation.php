<?php

namespace App\Models;

use App\Casts\PostbusAddressCast;
use App\Enums\OrganisationType;
use App\Models\Traits\HasUuid;
use App\Models\Users\OrganiserUser;
use App\Services\LocatieserverService;
use App\ValueObjects\Pdok\BagObject;
use App\ValueObjects\PostbusAddress;
use Database\Factories\OrganisationFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

/**
 * @property PostbusAddress|null $postbus_address
 */
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
        'postbus_address',
        'email',
        'phone',
    ];

    protected $appends = [
        'bag_address',
    ];

    public function isPostbus(): bool
    {
        return $this->postbus_address !== null;
    }

    /** @return Attribute<BagObject|null, void> */
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

    protected function casts(): array
    {
        return [
            'type' => OrganisationType::class,
            'postbus_address' => PostbusAddressCast::class,
        ];
    }
}
