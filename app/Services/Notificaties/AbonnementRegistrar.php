<?php

declare(strict_types=1);

namespace App\Services\Notificaties;

use App\Models\Municipality;
use App\Models\ZgwAbonnement;
use App\Services\Zgw\ZgwConnectionResolver;
use Illuminate\Support\Facades\URL;
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
     * The remote abonnement registered on this connection's Notificaties API for
     * our callback URL, or null when none is registered yet.
     *
     * @return array<string, mixed>|null
     */
    public function findRemote(NotificatiesApi $api, string $callbackUrl): ?array
    {
        /** @var array<string, mixed>|null $match */
        $match = collect($api->abonnementen())
            ->first(fn (array $abonnement): bool => ($abonnement['callbackUrl'] ?? null) === $callbackUrl);

        return $match;
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

        $callbackUrl = $this->callbackUrl();

        if ($dryRun) {
            return AbonnementRegistrationOutcome::DryRun;
        }

        $token = $this->issuer->issue();

        $existing = $this->findRemote($api, $callbackUrl);

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

        ZgwAbonnement::updateOrCreate(
            ['connection' => $connectionName],
            [
                'municipality_id' => ZgwAbonnement::municipalityIdFromConnection($connectionName),
                'notificaties_base_url' => $baseUrl,
                'callback_url' => $callbackUrl,
                'abonnement_url' => $abonnementUrl,
                'token_id' => $token->tokenId,
                'expires_at' => $token->expiresAt,
                'last_renewed_at' => now(),
            ],
        );

        return $outcome;
    }
}
