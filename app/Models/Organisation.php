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

    public function isPersonal(): bool
    {
        return $this->type === OrganisationType::Personal;
    }

    /**
     * How long a lookup that produced no address is remembered. Short, because
     * an unreachable Locatieserver must never blank out an address for good;
     * long enough that an outage does not turn every render of every zaak page
     * into another request that has to time out first.
     */
    private const BAG_ADDRESS_MISS_TTL = 60;

    /** @return Attribute<BagObject|null, void> */
    protected function bagAddress(): Attribute
    {
        return Attribute::make(
            get: function ($value, array $attributes) {
                if (! isset($attributes['bag_id']) || ! $attributes['bag_id']) {
                    return null;
                }

                $key = "organisation.{$this->id}.{$attributes['bag_id']}";
                $cached = Cache::get($key);

                if ($cached instanceof BagObject) {
                    return $cached;
                }

                // `false` records a recent lookup that produced nothing: PDOK
                // does not know the BAG id, or it could not be reached. That
                // outcome is cached briefly and never forever, so one failed
                // request cannot leave the address empty permanently.
                if ($cached === false) {
                    return null;
                }

                $bagObject = (new LocatieserverService)->getBagObjectById($attributes['bag_id']);

                if ($bagObject === null) {
                    Cache::put($key, false, self::BAG_ADDRESS_MISS_TTL);

                    return null;
                }

                Cache::forever($key, $bagObject);

                return $bagObject;
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
