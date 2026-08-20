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
    private float $connectTimeout;

    private float $timeout;

    public function __construct(private array $config = [])
    {
        $this->config = config('services.locatieserver');
        $this->connectTimeout = (float) $this->config['connect_timeout'];
        $this->timeout = (float) $this->config['timeout'];
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
        $clone->connectTimeout = (float) $this->config['background_connect_timeout'];
        $clone->timeout = (float) $this->config['background_timeout'];

        return $clone;
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
        try {
            return Http::connectTimeout($this->connectTimeout)
                ->timeout($this->timeout)
                ->get($this->config['base_url'].$path, $query);
        } catch (ConnectionException $exception) {
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
            'fl' => 'id type centroide_ll weergavenaam straatnaam postcode huisnummer woonplaatsnaam gemeentecode huisletter huisnummertoevoeging',
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
            'fl' => 'id type centroide_ll weergavenaam straatnaam postcode huisnummer woonplaatsnaam gemeentecode huisletter huisnummertoevoeging',
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
            'fl' => 'id type centroide_ll weergavenaam straatnaam postcode huisnummer woonplaatsnaam gemeentecode huisletter huisnummertoevoeging',
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
