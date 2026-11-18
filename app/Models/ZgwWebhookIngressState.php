<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * The state of the shared Open Notificaties webhook ingress, as a single row.
 *
 * Every ZGW connection posts to the same endpoint, so there is one state for the
 * ingress as a whole. It answers the two questions the logs cannot: when did a
 * delivery last get through, and have we been refusing deliveries in a row.
 *
 * Only counters and timestamps are stored. Request metadata stays in the log,
 * and request bodies and URLs are stored nowhere, because ZGW query filters can
 * carry personal data.
 *
 * @property Carbon|null $last_accepted_at
 * @property int $consecutive_rejections
 * @property Carbon|null $rejection_streak_started_at
 * @property int|null $last_rejection_status
 * @property Carbon|null $last_rejection_at
 */
class ZgwWebhookIngressState extends Model
{
    /**
     * The identifier of the one row this model ever has. Every write targets this
     * key, so concurrent webhook calls cannot leave two competing states behind.
     */
    public const SINGLETON_ID = 1;

    protected $fillable = [
        'last_accepted_at',
        'consecutive_rejections',
        'rejection_streak_started_at',
        'last_rejection_status',
        'last_rejection_at',
    ];

    protected function casts(): array
    {
        return [
            'last_accepted_at' => 'datetime',
            'consecutive_rejections' => 'integer',
            'rejection_streak_started_at' => 'datetime',
            'last_rejection_status' => 'integer',
            'last_rejection_at' => 'datetime',
        ];
    }

    /**
     * The current state, or null when nothing has ever been recorded. Readers get
     * null rather than an empty row so that "no data" stays distinguishable from
     * "data that happens to be zero".
     */
    public static function current(): ?self
    {
        return static::query()->find(self::SINGLETON_ID);
    }

    /**
     * Record that a delivery was accepted: the ingress works, so any rejection
     * streak is over.
     */
    public static function recordAccepted(): void
    {
        static::query()
            ->whereKey(self::ensureRow()->getKey())
            ->update([
                'last_accepted_at' => now(),
                'consecutive_rejections' => 0,
                'rejection_streak_started_at' => null,
                'updated_at' => now(),
            ]);
    }

    /**
     * Record that a delivery was refused.
     *
     * The streak start is only stamped on the first rejection after an accepted
     * delivery, so it measures how long the ingress has been refusing rather than
     * when the last attempt arrived. The counter is raised with an atomic
     * increment instead of a read-modify-write, so a burst of refused calls
     * cannot undercount itself.
     */
    public static function recordRejection(int $status): void
    {
        $key = self::ensureRow()->getKey();

        static::query()
            ->whereKey($key)
            ->whereNull('rejection_streak_started_at')
            ->update(['rejection_streak_started_at' => now()]);

        static::query()
            ->whereKey($key)
            ->increment('consecutive_rejections', 1, [
                'last_rejection_status' => $status,
                'last_rejection_at' => now(),
            ]);
    }

    /**
     * The singleton row, created on first use.
     *
     * Two simultaneous webhook calls can both find it missing, so the losing
     * insert is expected to collide on the primary key. Recording the state must
     * never be the reason a webhook call fails, so that collision is absorbed and
     * the row the winner wrote is used.
     */
    private static function ensureRow(): self
    {
        $existing = static::current();

        if ($existing instanceof self) {
            return $existing;
        }

        try {
            $fresh = new self;
            $fresh->forceFill(['id' => self::SINGLETON_ID])->save();

            return $fresh;
        } catch (QueryException) {
            return static::query()->findOrFail(self::SINGLETON_ID);
        }
    }
}
