<?php

namespace Database\Factories;

use App\Enums\OrganisationType;
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
            'type' => fake()->randomElement(OrganisationType::cases()),
            'name' => fake()->company,
            'coc_number' => fake()->numerify('########'),
            'address' => fake()->address,
            'email' => 'test@domain.com',
            'phone' => fake()->phoneNumber,
        ];
    }
}
