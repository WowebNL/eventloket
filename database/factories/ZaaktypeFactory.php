<?php

namespace Database\Factories;

use App\Models\Zaaktype;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Zaaktype>
 */
class ZaaktypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->sentence,
            'zgw_zaaktype_url' => fake()->url,
            'is_active' => fake()->boolean,
        ];
    }
}
