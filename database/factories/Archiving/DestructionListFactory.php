<?php

namespace Database\Factories\Archiving;

use App\Enums\DestructionListStatus;
use App\Models\Archiving\DestructionList;
use App\Models\Municipality;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DestructionList>
 */
class DestructionListFactory extends Factory
{
    protected $model = DestructionList::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'municipality_id' => Municipality::factory(),
            'name' => 'Vernietigingslijst '.fake()->unique()->word(),
            'status' => DestructionListStatus::Draft,
        ];
    }

    public function readyToReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DestructionListStatus::ReadyToReview,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DestructionListStatus::Approved,
            'reviewed_at' => now(),
            'approved_at' => now(),
        ]);
    }

    public function deleting(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DestructionListStatus::Deleting,
            'reviewed_at' => now(),
            'approved_at' => now(),
            'confirmed_at' => now(),
            'coordinator_name' => fake()->name(),
            'coordinator_function' => 'Archiefcoördinator',
            'destruction_method' => config('archiving.destruction_method'),
        ]);
    }

    public function failed(): static
    {
        return $this->deleting()->state(fn (array $attributes) => [
            'status' => DestructionListStatus::Failed,
        ]);
    }
}
