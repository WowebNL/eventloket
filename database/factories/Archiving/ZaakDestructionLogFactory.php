<?php

namespace Database\Factories\Archiving;

use App\Models\Archiving\ZaakDestructionLog;
use App\Models\Municipality;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ZaakDestructionLog>
 */
class ZaakDestructionLogFactory extends Factory
{
    protected $model = ZaakDestructionLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'municipality_id' => Municipality::factory(),
            'zgw_connection' => 'main',
            'zgw_zaak_url' => 'https://zgw.example.com/zaken/api/v1/zaken/'.fake()->unique()->uuid(),
            'zaaknummer' => 'ZAAK-'.fake()->unique()->numberBetween(1000, 9999),
            'zaaktype_naam' => 'Evenementenvergunning',
            'destroyed_at' => now(),
            'reported_at' => null,
        ];
    }

    /**
     * Already rolled up into a report.
     */
    public function reported(): static
    {
        return $this->state(fn (array $attributes): array => [
            'reported_at' => now(),
        ]);
    }
}
