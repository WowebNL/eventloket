<?php

namespace Database\Factories;

use App\Enums\OrganisationType;
use App\Models\Organisation;
use App\ValueObjects\PostbusAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organisation>
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
            'type' => OrganisationType::Business,
            'name' => fake()->company,
            'coc_number' => (string) fake()->numberBetween(10000000, 99999999),
            'address' => fake()->address,
            'email' => 'test@domain.com',
            'phone' => fake()->phoneNumber,
        ];
    }

    /**
     * State for a personal environment ("Mijn omgeving"): no Chamber of
     * Commerce number, created when a user registers without a company.
     */
    public function personal(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => OrganisationType::Personal,
            'name' => 'Mijn omgeving',
            'coc_number' => null,
        ]);
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
