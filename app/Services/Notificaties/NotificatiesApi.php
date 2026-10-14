<?php

declare(strict_types=1);

namespace App\Services\Notificaties;

use Illuminate\Support\Facades\Http;
use Woweb\Zgw\Facades\Zgw;

/**
 * Thin wrapper around the Open Notificaties "abonnement" endpoints for a single
 * ZGW connection. Authentication reuses the connection's own ZGW JWT (the same
 * credentials used for the other components), and the base URL comes from the
 * connection's `notificaties` config. Reading the base URL throws when the
 * connection has no notificaties URL configured, which the callers treat as
 * "skip this connection".
 */
final class NotificatiesApi
{
    public function __construct(private readonly string $connectionName) {}

    /**
     * The configured notificaties base URL (with trailing slash). Throws a
     * Woweb\Zgw\Exceptions\InvalidConfigurationException when not configured.
     */
    public function baseUrl(): string
    {
        return Zgw::connection($this->connectionName)->getBaseUrl('notificaties');
    }

    /**
     * The existing abonnementen registered on this Notificaties API.
     *
     * @return array<int, array<string, mixed>>
     */
    public function abonnementen(): array
    {
        $response = Http::withHeaders($this->headers())->get($this->baseUrl().'abonnement');
        $response->throw();

        $json = $response->json();

        if (isset($json['results']) && is_array($json['results'])) {
            return $json['results'];
        }

        return is_array($json) ? $json : [];
    }

    /**
     * Fetch a single abonnement by its resource URL.
     *
     * @return array<string, mixed>
     */
    public function show(string $abonnementUrl): array
    {
        $this->assertUrlAllowed($abonnementUrl);

        $response = Http::withHeaders($this->headers())->get($abonnementUrl);
        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * Publish a notification onto the connection's Notificaties API, which fans
     * it out to every abonnement subscribed to the channel. Used by the
     * round-trip probe to confirm our callback is reachable.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function publish(array $payload): array
    {
        $response = Http::withHeaders($this->headers())->post($this->baseUrl().'notificaties', $payload);
        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createAbonnement(array $payload): array
    {
        $response = Http::withHeaders($this->headers())->post($this->baseUrl().'abonnement', $payload);
        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function patchAbonnement(string $abonnementUrl, array $payload): array
    {
        $this->assertUrlAllowed($abonnementUrl);

        $response = Http::withHeaders($this->headers())->patch($abonnementUrl, $payload);
        $response->throw();

        return $response->json() ?? [];
    }

    public function deleteAbonnement(string $abonnementUrl): void
    {
        $this->assertUrlAllowed($abonnementUrl);

        Http::withHeaders($this->headers())->delete($abonnementUrl)->throw();
    }

    /**
     * Guard the abonnement URL against the connection's origin allowlist before
     * sending the connection's bearer token to it. The abonnement URL comes from
     * the remote Notificaties listing or stored state, so a compromised backend
     * cannot redirect a credentialed request at an internal host.
     */
    private function assertUrlAllowed(string $url): void
    {
        Zgw::connection($this->connectionName)->assertUrlAllowed($url);
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return Zgw::connection($this->connectionName)->getHeaders();
    }
}
