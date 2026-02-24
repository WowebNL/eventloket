<?php

namespace Database\Factories;

use App\Enums\OrganisationType;
use App\ValueObjects\PostbusAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Organisation>
 */
class OrganisationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(OrganisationType::cases()),
            'name' => fake()->company,
            'coc_number' => fake()->numerify('########'),
            'address' => fake()->address,
            'email' => 'test@domain.com',
            'phone' => fake()->phoneNumber,
        ];
    }

    /**
     * State for an organisation with a postbus address.
     */
    public function postbus(string $postbusnummer = '123', string $postcode = '5678CD', string $woonplaatsnaam = 'Rotterdam'): static
    {
        $postbusAddress = new PostbusAddress(
            postbusnummer: $postbusnummer,
            postcode: $postcode,
            woonplaatsnaam: $woonplaatsnaam,
        );

        return $this->state(fn (array $attributes) => [
            'postbus_address' => $postbusAddress,
            'bag_id' => null,
            'address' => $postbusAddress->weergavenaam(),
        ]);
    }
}
