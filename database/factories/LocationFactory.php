<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Location>
 */
class LocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'municipality_id' => \App\Models\Municipality::factory(),
            'name' => fake()->company().' '.fake()->randomElement(['Hall', 'Center', 'Building', 'Complex']),
            'postal_code' => fake()->postcode(),
            'house_number' => (string) fake()->numberBetween(1, 999),
            'house_letter' => fake()->optional(0.3)->randomLetter(),
            'house_number_addition' => fake()->optional(0.2)->bothify('##'),
            'street_name' => fake()->streetName(),
            'city_name' => fake()->city(),
            'active' => true,
            'geometry' => null,
        ];
    }
}
