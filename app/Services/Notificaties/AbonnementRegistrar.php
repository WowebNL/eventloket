<?php

declare(strict_types=1);

namespace App\Services\Notificaties;

use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use App\Models\ZgwAbonnement;
use App\Services\Zgw\ZgwConnectionResolver;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\URL;
use Throwable;
use Woweb\Zgw\Exceptions\InvalidConfigurationException;

/**
 * Registers (or refreshes) the Open Notificaties abonnement for a single ZGW
 * connection: it issues a scoped webhook token, creates or patches the remote
 * abonnement on our shared callback, and persists the {@see ZgwAbonnement} row.
 *
 * Lives in one place so the registration command and the per-connection UI
 * action share the exact same path (and the health check can reuse KANALEN and
 * the callback URL to verify a registration).
 */
class AbonnementRegistrar
{
    /**
     * Channels the webhook subscribes to. Empty filters = every notification on
     * the channel. Only real ZGW channels are listed: subscribing to a channel
     * the Notificaties server does not know (e.g. a synthetic "test" channel)
     * makes it reject the whole abonnement with a validation error.
     *
     * @var list<string>
     */
    public const KANALEN = ['zaken', 'besluiten', 'documenten', 'zaaktypen'];

    public function __construct(private readonly WebhookTokenIssuer $issuer) {}

    /**
     * The connection names to act on: an explicit single connection, otherwise
     * the shared "main" connection plus every municipality with its own ZGW
     * connection.
     *
     * @return list<string>
     */
    public static function connectionNames(ZgwConnectionResolver $resolver, ?string $explicit = null): array
    {
        if (is_string($explicit) && $explicit !== '') {
            // Resolving through the municipality also registers the per-connection
            // runtime config (config('zgw.connections.gemeente_{id}')); without
            // this an explicit "gemeente_{id}" would have no config and look like
            // it has no notificaties URL.
            if (preg_match('/^gemeente_(\d+)$/', $explicit, $matches) === 1) {
                $municipality = Municipality::find((int) $matches[1]);

                if ($municipality !== null) {
                    return [$resolver->forMunicipality($municipality)];
                }
            }

            return [$explicit];
        }

        $names = [ZgwConnectionResolver::DEFAULT_CONNECTION];

        foreach (Municipality::has('zgwConnection')->get() as $municipality) {
            $name = $resolver->forMunicipality($municipality);

            if ($name !== ZgwConnectionResolver::DEFAULT_CONNECTION) {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * The public callback URL the Notificaties API calls back on.
     */
    public function callbackUrl(): string
    {
        return URL::route('api.open-notifications.listen');
    }

    /**
     * The remote abonnement that belongs to this connection, or null when it has
     * none yet.
     *
     * The connection's own stored abonnement URL is authoritative. Only when there
     * is none (or the Notificaties API no longer knows it) does this fall back to
     * matching on the callback URL, and then it skips every abonnement another
     * connection has stored: the callback URL is the same for all connections, so
     * a match on it alone could hand this connection someone else's abonnement.
     *
     * @return array<string, mixed>|null
     */
    public function findRemote(NotificatiesApi $api, string $callbackUrl, string $connectionName): ?array
    {
        return $this->locateRemote($api, $callbackUrl, $connectionName)['own'];
    }

    /**
     * Whether this connection may not hold an abonnement on the given
     * Notificaties API because another connection already does. Null when it may.
     *
     * The main connection always keeps the abonnement on its own instance. Any
     * other connection is refused when its notificaties URL is inherited from
     * main, points at main's instance, or points at an instance on which an
     * earlier registered connection holds the abonnement.
     */
    public function sharedInstance(string $connectionName, string $baseUrl): ?SharedNotificatiesInstance
    {
        if ($connectionName === ZgwConnectionResolver::DEFAULT_CONNECTION) {
            return null;
        }

        if ($this->inheritsNotificatiesUrl($connectionName)) {
            return new SharedNotificatiesInstance(ZgwConnectionResolver::DEFAULT_CONNECTION, inherited: true);
        }

        $instance = self::normaliseUrl($baseUrl);
        $mainUrl = config('zgw.connections.'.ZgwConnectionResolver::DEFAULT_CONNECTION.'.urls.notificaties');

        if (is_string($mainUrl) && $mainUrl !== '' && self::normaliseUrl($mainUrl) === $instance) {
            return new SharedNotificatiesInstance(ZgwConnectionResolver::DEFAULT_CONNECTION, inherited: false);
        }

        // The earliest registration on an instance owns it, so two connections
        // that were both registered there before this check existed do not end
        // up refusing each other.
        $ownId = ZgwAbonnement::query()->where('connection', $connectionName)->value('id');

        $owner = ZgwAbonnement::query()
            ->where('connection', '!=', $connectionName)
            ->whereNotNull('abonnement_url')
            ->when($ownId !== null, fn ($query) => $query->where('id', '<', $ownId))
            ->orderBy('id')
            ->get(['connection', 'notificaties_base_url'])
            ->first(fn (ZgwAbonnement $row): bool => self::normaliseUrl($row->notificaties_base_url) === $instance);

        return $owner === null ? null : new SharedNotificatiesInstance($owner->connection, inherited: false);
    }

    /**
     * Register or refresh the abonnement for the given connection. Returns what
     * happened so callers can report it; throws on a real API failure.
     */
    public function register(string $connectionName, bool $dryRun = false): AbonnementRegistrationOutcome
    {
        $api = new NotificatiesApi($connectionName);

        try {
            $baseUrl = $api->baseUrl();
        } catch (InvalidConfigurationException) {
            return AbonnementRegistrationOutcome::SkippedNoNotificatiesUrl;
        }

        if ($this->sharedInstance($connectionName, $baseUrl) !== null) {
            return AbonnementRegistrationOutcome::RefusedSharedInstance;
        }

        $callbackUrl = $this->callbackUrl();

        if ($dryRun) {
            return AbonnementRegistrationOutcome::DryRun;
        }

        $previous = ZgwAbonnement::where('connection', $connectionName)->first();
        ['own' => $existing, 'claimed' => $claimed] = $this->locateRemote($api, $callbackUrl, $connectionName);

        // Our callback is already subscribed on this instance by another
        // connection, under a URL spelled differently from this one's. A second
        // abonnement would deliver every notification twice, and patching that one
        // would take it over, so refuse instead.
        if ($existing === null && $claimed) {
            return AbonnementRegistrationOutcome::RefusedSharedInstance;
        }

        $token = $this->issuer->issue();

        try {
            if (is_array($existing) && isset($existing['url'])) {
                $abonnementUrl = (string) $existing['url'];
                $api->patchAbonnement($abonnementUrl, ['auth' => 'Bearer '.$token->token]);
                $outcome = AbonnementRegistrationOutcome::Updated;
            } else {
                $created = $api->createAbonnement([
                    'callbackUrl' => $callbackUrl,
                    'auth' => 'Bearer '.$token->token,
                    'kanalen' => array_map(fn (string $naam): array => ['naam' => $naam, 'filters' => (object) []], self::KANALEN),
                ]);
                $abonnementUrl = isset($created['url']) ? (string) $created['url'] : null;
                $outcome = AbonnementRegistrationOutcome::Created;
            }
        } catch (Throwable $e) {
            // Registration failed, so retire the credential we just minted rather
            // than leaving an orphaned client_credentials client behind.
            $this->issuer->revoke($token->tokenId, $token->clientId);

            throw $e;
        }

        ZgwAbonnement::updateOrCreate(
            ['connection' => $connectionName],
            [
                'municipality_id' => ZgwAbonnement::municipalityIdFromConnection($connectionName),
                'notificaties_base_url' => $baseUrl,
                'callback_url' => $callbackUrl,
                'abonnement_url' => $abonnementUrl,
                'token_id' => $token->tokenId,
                'client_id' => $token->clientId,
                'expires_at' => $token->expiresAt,
                'last_renewed_at' => now(),
            ],
        );

        // The new credential is registered, so any previous one can be retired.
        $this->issuer->revoke($previous?->token_id, $previous?->client_id);

        return $outcome;
    }

    /**
     * Find this connection's remote abonnement, and report whether the instance
     * carries an abonnement on our callback that another connection has stored.
     *
     * @return array{own: array<string, mixed>|null, claimed: bool}
     */
    private function locateRemote(NotificatiesApi $api, string $callbackUrl, string $connectionName): array
    {
        $stored = ZgwAbonnement::query()->where('connection', $connectionName)->value('abonnement_url');

        // A stored abonnement only counts when it lives on the instance this
        // connection is configured for now. After the notificaties URL changed,
        // the stored one belongs to the previous instance (possibly another
        // connection's), so it is neither read nor patched.
        if (is_string($stored) && $stored !== '' && self::isOnInstance($stored, $api->baseUrl())) {
            try {
                $remote = $api->show($stored);

                if ($remote !== []) {
                    return ['own' => array_merge(['url' => $stored], $remote), 'claimed' => false];
                }
            } catch (RequestException $e) {
                // Gone on the remote side: look for it by callback below. Any other
                // failure is real and must not lead to a second abonnement.
                if ($e->response->status() !== 404) {
                    throw $e;
                }
            }
        }

        /** @var list<string> $others */
        $others = ZgwAbonnement::query()
            ->where('connection', '!=', $connectionName)
            ->whereNotNull('abonnement_url')
            ->pluck('abonnement_url')
            ->all();

        $onCallback = collect($api->abonnementen())
            ->filter(fn (mixed $abonnement): bool => is_array($abonnement) && ($abonnement['callbackUrl'] ?? null) === $callbackUrl);

        /** @var array<string, mixed>|null $own */
        $own = $onCallback->first(fn (array $abonnement): bool => ! in_array($abonnement['url'] ?? null, $others, true));

        return [
            'own' => $own,
            'claimed' => $onCallback->contains(fn (array $abonnement): bool => in_array($abonnement['url'] ?? null, $others, true)),
        ];
    }

    /**
     * Whether a per-municipality connection leaves its notificaties URL empty and
     * so inherits the main connection's.
     */
    private function inheritsNotificatiesUrl(string $connectionName): bool
    {
        $municipalityId = ZgwAbonnement::municipalityIdFromConnection($connectionName);

        if ($municipalityId === null) {
            return false;
        }

        $connection = MunicipalityZgwConnection::query()
            ->where('municipality_id', $municipalityId)
            ->first(['notificaties_url']);

        if ($connection === null) {
            return false;
        }

        $url = $connection->notificaties_url;

        return ! is_string($url) || trim($url) === '';
    }

    /**
     * Whether an abonnement URL belongs to the Notificaties API at the given base
     * URL.
     */
    public static function isOnInstance(string $abonnementUrl, string $baseUrl): bool
    {
        return str_starts_with(strtolower(trim($abonnementUrl)), self::normaliseUrl($baseUrl).'/');
    }

    private static function normaliseUrl(string $url): string
    {
        return rtrim(strtolower(trim($url)), '/');
    }
}
