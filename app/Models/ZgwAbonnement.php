<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ZgwAbonnementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An Open Notificaties abonnement registered against a single ZGW connection.
 *
 * The token the remote Notificaties API presents on our shared webhook is a
 * scoped Passport access token; {@see expires_at} drives the auto-renew job
 * that rotates it before it lapses.
 *
 * @property string $connection
 * @property int|null $municipality_id
 * @property string $notificaties_base_url
 * @property string $callback_url
 * @property string|null $abonnement_url
 * @property string|null $token_id
 * @property string|null $client_id
 * @property Carbon|null $expires_at
 * @property Carbon|null $last_renewed_at
 */
class ZgwAbonnement extends Model
{
    /** @use HasFactory<ZgwAbonnementFactory> */
    use HasFactory;

    protected $fillable = [
        'connection',
        'municipality_id',
        'notificaties_base_url',
        'callback_url',
        'abonnement_url',
        'token_id',
        'client_id',
        'expires_at',
        'last_renewed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_renewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Municipality, $this> */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /**
     * Abonnementen whose token expires on or before the given moment, so the
     * auto-renew job can rotate them ahead of time. Records without an expiry
     * (or without a registered abonnement url to patch) are skipped.
     *
     * @param  Builder<ZgwAbonnement>  $query
     */
    public function scopeExpiringBefore(Builder $query, \DateTimeInterface $moment): void
    {
        $query->whereNotNull('abonnement_url')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $moment);
    }

    /**
     * Derive the municipality id a connection name belongs to. A per-municipality
     * connection is named "gemeente_{id}"; the shared "main" connection (and any
     * other name) is not attributable to a single municipality.
     */
    public static function municipalityIdFromConnection(string $connection): ?int
    {
        return preg_match('/^gemeente_(\d+)$/', $connection, $matches) === 1
            ? (int) $matches[1]
            : null;
    }
}
