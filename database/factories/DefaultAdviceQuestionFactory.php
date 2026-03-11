<?php

namespace Database\Factories;

use App\Models\Advisory;
use App\Models\DefaultAdviceQuestion;
use App\Models\Municipality;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DefaultAdviceQuestion>
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
            'municipality_id' => Municipality::factory(),
            'advisory_id' => Advisory::factory(),
            'risico_classificatie' => fake()->randomElement(['A', 'B', 'C']),
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'response_deadline_days' => fake()->numberBetween(7, 30),
        ];
    }
}
