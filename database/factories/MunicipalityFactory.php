<?php

namespace Database\Factories;

use App\Models\Municipality;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Municipality>
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
