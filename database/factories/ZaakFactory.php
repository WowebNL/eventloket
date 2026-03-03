<?php

namespace Database\Factories;

use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Illuminate\Database\Eloquent\Factories\Factory;
use Tests\Fakes\ZgwHttpFake;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Zaak>
 */
class ZaakFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => 'ZAAK-'.fake()->unique()->randomNumber(5),
            'zgw_zaak_url' => fake()->unique()->url,
            'data_object_url' => fake()->url,
            'reference_data' => new ZaakReferenceData(
                now(),
                now()->addDay(),
                now(),
                'Ontvangen',
                ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/1',
                'A',
                'Test locatie',
                'Test event'
            ),
        ];
    }
}
