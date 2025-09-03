<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Zaak>
 */
class ZaakFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => 'ZAAK-'.fake()->unique()->randomNumber(5),
            'zgw_zaak_url' => fake()->url,
            'data_object_url' => fake()->url,
        ];
    }
}
