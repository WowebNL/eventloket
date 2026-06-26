<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\MunicipalityZgwConnectionObserver;
use Database\Factories\MunicipalityZgwConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * A municipality's own ZGW connection (its own OpenZaak / RX Mission / etc.).
 *
 * One row per municipality; the resolver registers it at runtime as the
 * connection "gemeente_{id}". {@see buildConfig()} maps the row to the
 * woweb/laravel-zgw-client config shape and inherits every unset value from the
 * global "main" connection, so a partial row still produces a working config.
 *
 * @property int $municipality_id
 * @property string|null $name
 * @property string|null $zaken_url
 * @property string|null $catalogi_url
 * @property string|null $documenten_url
 * @property string|null $besluiten_url
 * @property string|null $autorisaties_url
 * @property string|null $notificaties_url
 * @property string|null $version
 * @property string|null $client_id
 * @property string|null $client_secret
 * @property string|null $user_id
 * @property string|null $user_representation
 * @property array<int, string>|null $allowed_hosts
 * @property string|null $bronorganisatie_rsin
 * @property array<string, mixed>|null $vertrouwelijkheid_map
 * @property string|null $eigenschap_date_format
 */
#[ObservedBy(MunicipalityZgwConnectionObserver::class)]
class MunicipalityZgwConnection extends Model
{
    /** @use HasFactory<MunicipalityZgwConnectionFactory> */
    use HasFactory;

    protected $fillable = [
        'municipality_id',
        'name',
        'zaken_url',
        'catalogi_url',
        'documenten_url',
        'besluiten_url',
        'autorisaties_url',
        'notificaties_url',
        'version',
        'client_id',
        'client_secret',
        'user_id',
        'user_representation',
        'allowed_hosts',
        'bronorganisatie_rsin',
        'vertrouwelijkheid_map',
        'eigenschap_date_format',
    ];

    protected $hidden = [
        'client_secret',
    ];

    protected function casts(): array
    {
        return [
            'client_secret' => 'encrypted',
            'allowed_hosts' => 'array',
            'vertrouwelijkheid_map' => 'array',
        ];
    }

    /** @return BelongsTo<Municipality, $this> */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /**
     * Map this row onto the woweb/laravel-zgw-client connection config shape,
     * inheriting every unset value from the global "main" connection.
     *
     * The HS256 signing secret must be at least the configured minimum length
     * (32 bytes); a shorter secret is rejected here so the resolver can fall
     * back to "main" rather than letting the package throw a WeakSecretException
     * later, at an arbitrary call site.
     *
     * @return array<string, mixed>
     */
    public function buildConfig(): array
    {
        /** @var array<string, mixed> $main */
        $main = config('zgw.connections.main', []);

        $config = $main;
        $config['urls'] = array_merge(
            is_array($main['urls'] ?? null) ? $main['urls'] : [],
            array_filter([
                'zaken' => $this->zaken_url,
                'catalogi' => $this->catalogi_url,
                'documenten' => $this->documenten_url,
                'besluiten' => $this->besluiten_url,
                'autorisaties' => $this->autorisaties_url,
                'notificaties' => $this->notificaties_url,
            ], static fn (?string $url): bool => is_string($url) && $url !== ''),
        );

        $overrides = [
            'version' => $this->version,
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'user_id' => $this->user_id,
            'user_representation' => $this->user_representation,
            'allowed_hosts' => $this->allowed_hosts,
            'bronorganisatie_rsin' => $this->bronorganisatie_rsin,
            'vertrouwelijkheid_map' => $this->vertrouwelijkheid_map,
            'eigenschap_date_format' => $this->eigenschap_date_format,
        ];

        foreach ($overrides as $key => $value) {
            if ($value !== null && $value !== '' && $value !== []) {
                $config[$key] = $value;
            }
        }

        $this->assertSecretMeetsMinimumLength($config);

        return $config;
    }

    /**
     * Backstop the package's own ClientSecretValidator so an invalid secret is
     * caught while building the config (where the resolver can recover) rather
     * than when the connection is first used.
     *
     * @param  array<string, mixed>  $config
     */
    private function assertSecretMeetsMinimumLength(array $config): void
    {
        $secret = (string) ($config['client_secret'] ?? '');
        $minLength = (int) ($config['secret_rules']['min_length'] ?? 32);

        if (strlen($secret) < $minLength) {
            throw new RuntimeException(
                "ZGW client_secret for municipality {$this->municipality_id} is shorter than the required {$minLength} bytes."
            );
        }
    }
}
