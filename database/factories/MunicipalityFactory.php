<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Municipality>
 */
class MunicipalityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->city,
            'brk_identification' => fake()->unique()->bothify('GM###'),
            'geometry' => '{"type":"MultiPolygon","coordinates":[[[[0.5,0.5],[1.5,1.5],[1.5,1.5],[0.5,0.5]]]]}',
        ];
    }
}
