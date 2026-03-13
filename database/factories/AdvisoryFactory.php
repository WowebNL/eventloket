<?php

namespace Database\Factories;

use App\Models\Advisory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Advisory>
 */
class AdvisoryFactory extends Factory
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
        ];
    }
}
