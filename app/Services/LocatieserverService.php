<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class LocatieserverService
{
    public function __construct(private array $config = [])
    {
        $this->config = config('services.locatieserver');
    }

    /**
     * Get BRK identification by city.
     */
    public function getBrkIdentificationByCity(string $city): ?string
    {
        $url = $this->config['base_url'].'/search/v3_1/free';
        $httpResponse = Http::get($url, [
            'q' => $city,
            'fq' => 'type:(gemeente)',
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
}
