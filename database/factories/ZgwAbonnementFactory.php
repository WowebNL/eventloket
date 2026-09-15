<?php

namespace Database\Factories;

use App\Models\ZgwAbonnement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ZgwAbonnement>
 */
class ZgwAbonnementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $base = 'https://notificaties.example.com';

        return [
            'connection' => 'main',
            'municipality_id' => null,
            'notificaties_base_url' => $base.'/api/v1/',
            'callback_url' => 'https://eventloket.example.com/api/open-notifications/listen',
            'abonnement_url' => $base.'/api/v1/abonnement/'.fake()->uuid(),
            'token_id' => fake()->uuid(),
            'client_id' => fake()->uuid(),
            'expires_at' => now()->addYear(),
            'last_renewed_at' => null,
        ];
    }
}
