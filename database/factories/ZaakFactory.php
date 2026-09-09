<?php

namespace Database\Factories;

use App\Models\Zaak;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Illuminate\Database\Eloquent\Factories\Factory;
use Tests\Fakes\ZgwHttpFake;

/**
 * @extends Factory<Zaak>
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
                registratiedatum: now(),
                status_name: 'Ontvangen',
                statustype_url: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/1',
                start_evenement: now(),
                eind_evenement: now()->addDay(),
                risico_classificatie: 'A',
                naam_locatie_eveneme: 'Test locatie',
                naam_evenement: 'Test event',
            ),
        ];
    }
}
