<?php

namespace Database\Factories;

use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MunicipalityZgwConnection>
 */
class MunicipalityZgwConnectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The secret is deliberately >= 32 bytes so a freshly built connection
     * passes the HS256 signing-key floor.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $base = 'https://gemeente.example.com';

        return [
            'municipality_id' => Municipality::factory(),
            'zaken_url' => $base.'/zaken/api/v1/',
            'catalogi_url' => $base.'/catalogi/api/v1/',
            'documenten_url' => $base.'/documenten/api/v1/',
            'besluiten_url' => $base.'/besluiten/api/v1/',
            'autorisaties_url' => $base.'/autorisaties/api/v1/',
            'notificaties_url' => $base.'/notificaties/api/v1/',
            'version' => '1.5',
            'client_id' => 'gemeente-client',
            'client_secret' => 'gemeente-secret-at-least-32-bytes-long',
            'user_id' => 'gemeente-client',
            'user_representation' => 'Gemeente',
            'allowed_hosts' => [],
            'bronorganisatie_rsin' => null,
            'vertrouwelijkheid_map' => null,
            'lock_status_for_behandelaar' => false,
            'show_besluiten_tab' => true,
            'show_bestanden_tab' => true,
            'show_adviesvragen_tab' => true,
            'show_organisatievragen_tab' => true,
            'suppress_notifications' => false,
        ];
    }

    /**
     * A verified and activated (live) connection: the resolver routes the
     * municipality's ZGW traffic to it.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'last_verified_at' => now(),
            'activated_at' => now(),
        ]);
    }
}
