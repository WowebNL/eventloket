<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Notificaties\StaticWebhookTokenIssuer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Carbon;

/**
 * A short opaque webhook token ("elw.<lookup>.<secret>") issued to one ZGW
 * connection, see {@see StaticWebhookTokenIssuer}.
 *
 * Only the SHA-256 hash of the secret is stored. A revoked token keeps its
 * lookup and loses its hash, so it can never authenticate again but a refused
 * delivery that presents it is still recognised as our own credential.
 *
 * @property int $id
 * @property string $connection
 * @property string $lookup
 * @property string|null $secret_hash
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 */
class ZgwWebhookToken extends Model
{
    use Prunable;

    /**
     * Revoked and expired tokens are kept this long, so refused deliveries that
     * still present them keep counting towards the ingress health.
     */
    public const PRUNE_AFTER_DAYS = 30;

    protected $fillable = [
        'connection',
        'lookup',
        'secret_hash',
        'expires_at',
        'revoked_at',
    ];

    protected $hidden = [
        'secret_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Whether this token may authenticate right now, judged on the row alone.
     */
    public function isUsable(): bool
    {
        return $this->revoked_at === null
            && is_string($this->secret_hash) && $this->secret_hash !== ''
            && $this->expires_at->isFuture();
    }

    /**
     * @return Builder<ZgwWebhookToken>
     */
    public function prunable(): Builder
    {
        $cutoff = now()->subDays(self::PRUNE_AFTER_DAYS);

        return ZgwWebhookToken::query()->where(fn (Builder $query) => $query
            ->where('expires_at', '<', $cutoff)
            ->orWhere('revoked_at', '<', $cutoff));
    }
}
