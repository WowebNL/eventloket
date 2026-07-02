<?php

declare(strict_types=1);

namespace App\Models;

use App\Listeners\LogZgwRequest;
use App\Services\Zgw\ZgwConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One metadata row per ZGW HTTP exchange (no request/response bodies), written
 * by {@see LogZgwRequest} from the package's ZgwRequestSent event.
 *
 * @property string $connection
 * @property int|null $municipality_id
 * @property int|null $user_id
 * @property string $method
 * @property string $resource
 * @property int|null $status_code
 * @property bool $failed
 * @property int|null $duration_ms
 */
class ZgwRequestLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'connection',
        'municipality_id',
        'user_id',
        'method',
        'resource',
        'status_code',
        'failed',
        'duration_ms',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'failed' => 'boolean',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Municipality, $this> */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    /**
     * A human-friendly label for a connection name: the shared "main" connection
     * gets a translated label, a per-municipality connection ("gemeente_{id}")
     * resolves to that connection's display name (its name, or its zaken URL),
     * and anything unknown falls back to the raw connection name.
     */
    public static function connectionLabel(string $connection): string
    {
        if ($connection === ZgwConnectionResolver::DEFAULT_CONNECTION) {
            return __('admin/resources/zgw_request_log.connections.main');
        }

        return MunicipalityZgwConnection::displayNameForConnection($connection) ?? $connection;
    }
}
