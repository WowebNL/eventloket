<?php

namespace App\Services;

use App\ValueObjects\Pdok\BagObject;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LocatieserverService
{
    /**
     * No request may end up without a usable budget, so a configured value
     * below this floor is raised to it. Anything lower would fail before a
     * healthy Locatieserver has had a chance to answer.
     */
    private const MINIMUM_TIMEOUT = 0.5;

    /** Fallbacks mirroring the defaults in config/services.php. */
    private const DEFAULT_CONNECT_TIMEOUT = 2.0;

    private const DEFAULT_TIMEOUT = 5.0;

    private const DEFAULT_BACKGROUND_CONNECT_TIMEOUT = 5.0;

    private const DEFAULT_BACKGROUND_TIMEOUT = 20.0;

    private float $connectTimeout;

    private float $timeout;

    private bool $lastRequestUnreachable = false;

    public function __construct(private array $config = [])
    {
        $this->config = config('services.locatieserver');
        $this->connectTimeout = $this->resolveTimeout('connect_timeout', self::DEFAULT_CONNECT_TIMEOUT);
        $this->timeout = $this->resolveTimeout('timeout', self::DEFAULT_TIMEOUT);
    }

    /**
     * A copy of this service that waits longer on PDOK, for callers with no
     * user on the other end. Interactive callers keep the short budget: they
     * render a page or a Livewire update and cannot afford to sit out an
     * outage. Queued callers can, and for them a lookup that gives up too
     * early silently drops an address from the result instead of delaying it.
     */
    public function forBackgroundWork(): self
    {
        $clone = clone $this;
        $clone->connectTimeout = $this->resolveTimeout('background_connect_timeout', self::DEFAULT_BACKGROUND_CONNECT_TIMEOUT);
        $clone->timeout = $this->resolveTimeout('background_timeout', self::DEFAULT_BACKGROUND_TIMEOUT);

        return $clone;
    }

    /**
     * Whether the most recent request came back empty because Locatieserver
     * could not be reached, rather than because it knows no such address.
     * Both end up as null here, but they do not mean the same thing: a caller
     * that can try again later needs to tell them apart, because retrying a
     * transport failure eventually succeeds while retrying an unknown address
     * never will.
     */
    public function lastRequestWasUnreachable(): bool
    {
        return $this->lastRequestUnreachable;
    }

    /**
     * A timeout from config, falling back to the default when the value is not
     * a usable number. An empty environment variable and a config cache built
     * before these keys existed both leave a value that casts to 0.0, and
     * Guzzle reads 0 as "wait indefinitely" — the opposite of what these
     * settings are for, and worse than the framework defaults they replace.
     */
    private function resolveTimeout(string $key, float $default): float
    {
        $value = $this->config[$key] ?? null;

        if (! is_numeric($value)) {
            return $default;
        }

        return max(self::MINIMUM_TIMEOUT, (float) $value);
    }

    /**
     * Perform a Locatieserver request, degrading to null when PDOK cannot be
     * reached within the timeout budget.
     *
     * Every public method here already reports "not found" as null and every
     * caller handles that, so a transport failure must not escalate into an
     * exception. It used to: `Organisation::bag_address` resolves while a zaak
     * page is rendered, and a short PDOK outage took the whole page down with
     * a fatal error instead of showing the page without an address.
     *
     * @param  array<string, mixed>  $query
     */
    private function request(string $path, array $query): ?Response
    {
        $this->lastRequestUnreachable = false;

        try {
            return Http::connectTimeout($this->connectTimeout)
                ->timeout($this->timeout)
                ->get($this->config['base_url'].$path, $query);
        } catch (ConnectionException $exception) {
            $this->lastRequestUnreachable = true;

            // Guzzle appends the full request URI to its message and that URI
            // carries the address being looked up, so keep it out of the log.
            Log::warning('Locatieserver request failed; continuing without a result.', [
                'path' => $path,
                'reason' => Str::before($exception->getMessage(), ' for '),
            ]);

            return null;
        }
    }

    /**
     * Get BRK identification by postcode and house number.
     */
    public function getBrkIdentificationByPostcodeHuisnummer(string $postcode, string $huisnummer): ?string
    {
        $httpResponse = $this->request('/search/v3_1/free', [
            'q' => $postcode.' '.$huisnummer,
            'fq' => 'type:(adres)',
            'fl' => 'gemeentecode gemeentenaam',
        ]);

        if ($httpResponse !== null && $httpResponse->successful()) {
            $data = $httpResponse->json();
            if (Arr::has($data, ['response.docs.0.gemeentecode', 'response.docs.0.gemeentenaam'])) {
                return 'GM'.$data['response']['docs'][0]['gemeentecode'];
            }
        }

        return null;
    }

    public function reverse(float $lat, float $lon): ?array
    {
        $httpResponse = $this->request('/search/v3_1/reverse', [
            'lat' => $lat,
            'lon' => $lon,
            'fq' => 'type:(adres)',
            'fl' => 'id type centroide_ll weergavenaam straatnaam postcode huisnummer woonplaatsnaam gemeentecode huisletter huisnummertoevoeging nummeraanduiding_id',
        ]);

        if ($httpResponse !== null && $httpResponse->successful()) {
            $data = $httpResponse->json();
            if (Arr::has($data, ['response.docs.0'])) {
                return $data['response']['docs'][0];
            }
        }

        return null;
    }

    public function getBagObjectByPostcodeHuisnummer(string $postcode, string $huisnummer, ?string $huisletter = null, ?string $huisnummertoevoeging = null): ?BagObject
    {
        $httpResponse = $this->request('/search/v3_1/free', [
            'q' => $postcode.' '.$huisnummer,
            'fq' => 'type:(adres)',
            'fl' => 'id type centroide_ll weergavenaam straatnaam postcode huisnummer woonplaatsnaam gemeentecode huisletter huisnummertoevoeging nummeraanduiding_id',
        ]);

        if ($httpResponse !== null && $httpResponse->successful()) {
            $data = $httpResponse->json();
            $item = Arr::first($data['response']['docs'] ?? [], function ($item) use ($postcode, $huisnummer, $huisletter, $huisnummertoevoeging) {
                // PDOK's free-text search is fuzzy: a non-existent house number
                // still returns the closest matching address. Guard against that
                // by requiring the returned postcode and house number to match
                // the input exactly, otherwise a wrong address gets auto-filled.
                if (! $this->matchesPostcode($item, $postcode) || ! $this->matchesHuisnummer($item, $huisnummer)) {
                    return false;
                }

                if ($huisletter && $huisnummertoevoeging) {
                    if (isset($item['huisletter']) && $item['huisletter'] === $huisletter && isset($item['huisnummertoevoeging']) && $item['huisnummertoevoeging'] === $huisnummertoevoeging) {
                        return true;
                    }

                    return false;
                } elseif ($huisletter) {
                    if (isset($item['huisletter']) && $item['huisletter'] === $huisletter) {
                        return true;
                    }

                    return false;
                } elseif ($huisnummertoevoeging) {
                    if (isset($item['huisnummertoevoeging']) && $item['huisnummertoevoeging'] === $huisnummertoevoeging) {
                        return true;
                    }

                    return false;
                } else {
                    if (! isset($item['huisletter']) && ! isset($item['huisnummertoevoeging'])) {
                        return true;
                    }

                    return false;
                }
            });

            if ($item) {
                return new BagObject(...$item);
            }
        }

        return null;
    }

    /**
     * Whether the PDOK document's postcode equals the requested postcode,
     * ignoring casing and internal spacing ("6361 bz" matches "6361BZ").
     *
     * @param  array<string, mixed>  $item
     */
    private function matchesPostcode(array $item, string $postcode): bool
    {
        if (! isset($item['postcode'])) {
            return false;
        }

        return $this->normalizePostcode((string) $item['postcode']) === $this->normalizePostcode($postcode);
    }

    /**
     * Whether the PDOK document's house number equals the requested one. PDOK
     * may return the house number as an int, so both sides are compared as
     * trimmed strings.
     *
     * @param  array<string, mixed>  $item
     */
    private function matchesHuisnummer(array $item, string $huisnummer): bool
    {
        if (! isset($item['huisnummer'])) {
            return false;
        }

        return trim((string) $item['huisnummer']) === trim($huisnummer);
    }

    private function normalizePostcode(string $postcode): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', $postcode));
    }

    public function getBagObjectById(string $bagId): ?BagObject
    {
        $httpResponse = $this->request('/search/v3_1/lookup', [
            'id' => $bagId,
            'fl' => 'id type centroide_ll weergavenaam straatnaam postcode huisnummer woonplaatsnaam gemeentecode huisletter huisnummertoevoeging nummeraanduiding_id',
        ]);

        if ($httpResponse !== null && $httpResponse->successful()) {
            $data = $httpResponse->json();
            if (Arr::has($data, ['response.docs.0'])) {
                return new BagObject(...$data['response']['docs'][0]);
            }
        }

        return null;
    }
}
