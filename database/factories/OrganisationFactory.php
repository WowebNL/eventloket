<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Organisation>
 */
class OrganisationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company,
            'coc_number' => fake()->numerify('########'),
            'address' => fake()->address,
            'email' => fake()->companyEmail,
            'phone' => fake()->phoneNumber,
        ];
    }
}
