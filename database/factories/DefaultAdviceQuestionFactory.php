<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DefaultAdviceQuestion>
 */
class DefaultAdviceQuestionFactory extends Factory
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
            'advisory_id' => \App\Models\Advisory::factory(),
            'risico_classificatie' => fake()->randomElement(['A', 'B', 'C']),
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'response_deadline_days' => fake()->numberBetween(7, 30),
        ];
    }
}
