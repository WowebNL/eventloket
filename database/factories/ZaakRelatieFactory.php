<?php

namespace Database\Factories;

use App\Enums\ZaakRelatieType;
use App\Models\Zaak;
use App\Models\ZaakRelatie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ZaakRelatie>
 */
class ZaakRelatieFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'zaak_id' => Zaak::factory(),
            'gerelateerde_zaak_id' => Zaak::factory(),
            'type' => ZaakRelatieType::VervangtVooraankondiging,
        ];
    }
}
