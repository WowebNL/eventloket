<?php

namespace Database\Factories;

use App\Enums\MunicipalityVariableType;
use App\Models\Municipality;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MunicipalityVariable>
 */
class MunicipalityVariableFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(MunicipalityVariableType::cases());

        return [
            'municipality_id' => Municipality::factory(),
            'name' => fake()->words(2, true),
            'key' => fake()->slug(2),
            'type' => $type,
            'value' => $this->generateValueForType($type),
            'is_default' => fake()->boolean(30), // 30% chance of being default
        ];
    }

    /**
     * Generate a value appropriate for the given type
     */
    private function generateValueForType(MunicipalityVariableType $type): mixed
    {
        return match ($type) {
            MunicipalityVariableType::Text => fake()->sentence(),
            MunicipalityVariableType::Number => fake()->randomFloat(2, 0, 10000),
            MunicipalityVariableType::Boolean => fake()->boolean(),
            MunicipalityVariableType::DateRange => [
                'start' => fake()->date(),
                'end' => fake()->date(),
            ],
            MunicipalityVariableType::TimeRange => [
                'start' => fake()->time('H:i'),
                'end' => fake()->time('H:i'),
            ],
            MunicipalityVariableType::DateTimeRange => [
                'start' => fake()->dateTime()->format('Y-m-d H:i:s'),
                'end' => fake()->dateTime()->format('Y-m-d H:i:s'),
            ],
        };
    }

    /**
     * Create a default template variable (no municipality assigned)
     */
    public function defaultTemplate(): static
    {
        return $this->state(fn (array $attributes) => [
            'municipality_id' => null,
            'is_default' => true,
        ]);
    }

    /**
     * Create a custom municipality variable
     */
    public function custom(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => false,
        ]);
    }
}
