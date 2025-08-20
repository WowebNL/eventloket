<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class KadasterService
{
    public function __construct(private array $config = [])
    {
        $this->config = config('services.kadaster');
    }

    public function getGemeentegebiedByIdentification(string $brkIdentification): ?array
    {
        $response = null;
        $url = $this->config['base_url'].'/bestuurlijkegebieden/ogc/v1/collections/gemeentegebied/items';
        $httpResponse = Http::get($url, [
            'identificatie' => $brkIdentification,
            'limit' => 1,
            'f' => 'json',
        ]);

        if ($httpResponse->successful()) {
            $data = $httpResponse->json();
            $features = $data['features'] ?? [];

            foreach ($features as $feature) {
                if (Arr::hasAll($feature, ['properties.identificatie', 'geometry']) && $feature['properties']['identificatie'] === $brkIdentification) {
                    $response = $feature;
                }
            }
        }

        return $response;
    }
}
