<?php

namespace App\Services;

use App\ValueObjects\Pdok\BagObject;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class LocatieserverService
{
    public function __construct(private array $config = [])
    {
        $this->config = config('services.locatieserver');
    }

    /**
     * Get BRK identification by postcode and house number.
     */
    public function getBrkIdentificationByPostcodeHuisnummer(string $postcode, string $huisnummer): ?string
    {
        $url = $this->config['base_url'].'/search/v3_1/free';
        $httpResponse = Http::get($url, [
            'q' => $postcode.' '.$huisnummer,
            'fq' => 'type:(adres)',
            'fl' => 'gemeentecode gemeentenaam',
        ]);

        if ($httpResponse->successful()) {
            $data = $httpResponse->json();
            if (Arr::has($data, ['response.docs.0.gemeentecode', 'response.docs.0.gemeentenaam'])) {
                return 'GM'.$data['response']['docs'][0]['gemeentecode'];
            }
        }

        return null;
    }

    public function reverse(float $lat, float $lon): ?array
    {
        $url = $this->config['base_url'].'/search/v3_1/reverse';
        $httpResponse = Http::get($url, [
            'lat' => $lat,
            'lon' => $lon,
            'fq' => 'type:(adres)',
            'fl' => 'id type centroide_ll weergavenaam straatnaam postcode huisnummer woonplaatsnaam gemeentecode huisletter huisnummertoevoeging',
        ]);

        if ($httpResponse->successful()) {
            $data = $httpResponse->json();
            if (Arr::has($data, ['response.docs.0'])) {
                return $data['response']['docs'][0];
            }
        }

        return null;
    }

    public function getBagObjectByPostcodeHuisnummer(string $postcode, string $huisnummer, ?string $huisletter = null, ?string $huisnummertoevoeging = null): ?BagObject
    {
        $url = $this->config['base_url'].'/search/v3_1/free';
        $httpResponse = Http::get($url, [
            'q' => $postcode.' '.$huisnummer,
            'fq' => 'type:(adres)',
            'fl' => 'id type centroide_ll weergavenaam straatnaam postcode huisnummer woonplaatsnaam gemeentecode huisletter huisnummertoevoeging',
        ]);

        if ($httpResponse->successful()) {
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
        $url = $this->config['base_url'].'/search/v3_1/lookup';
        $httpResponse = Http::get($url, [
            'id' => $bagId,
            'fl' => 'id type centroide_ll weergavenaam straatnaam postcode huisnummer woonplaatsnaam gemeentecode huisletter huisnummertoevoeging',
        ]);

        if ($httpResponse->successful()) {
            $data = $httpResponse->json();
            if (Arr::has($data, ['response.docs.0'])) {
                return new BagObject(...$data['response']['docs'][0]);
            }
        }

        return null;
    }
}
