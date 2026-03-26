<?php

namespace Database\Factories;

use App\Models\Municipality;
use App\Models\ReportQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportQuestion>
 */
class ReportQuestionFactory extends Factory
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
            'order' => fake()->numberBetween(1, 10),
            'question' => fake()->sentence().'?',
            'is_active' => true,
            'placeholder_value' => null,
        ];
    }
}
