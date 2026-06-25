<?php

namespace Database\Factories;

use App\Models\TableState;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TableState>
 */
class TableStateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'table_key' => fake()->word(),
            'state' => [],
        ];
    }
}
