<?php

namespace Database\Factories\Archiving;

use App\Models\Archiving\DestructionReport;
use App\Models\Municipality;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DestructionReport>
 */
class DestructionReportFactory extends Factory
{
    protected $model = DestructionReport::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'municipality_id' => Municipality::factory(),
            'batch_number' => 'VL-1-'.now()->year.'-'.fake()->unique()->numberBetween(100, 999),
            'coordinator_name' => fake()->name(),
            'coordinator_function' => 'Archiefcoördinator',
            'destruction_method' => config('archiving.destruction_method'),
            'destruction_date' => now(),
            'items' => [],
            'total_count' => 0,
            'deleted_count' => 0,
            'failed_count' => 0,
            'skipped_count' => 0,
        ];
    }
}
