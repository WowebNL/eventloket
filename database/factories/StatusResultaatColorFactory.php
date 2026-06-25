<?php

namespace Database\Factories;

use App\Models\StatusResultaatColor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatusResultaatColor>
 */
class StatusResultaatColorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status_name' => fake()->unique()->word(),
            'resultaat' => null,
            'color' => fake()->hexColor(),
        ];
    }
}
