<?php

namespace Database\Factories;

use App\Enums\OrganisationRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrganisationInvite>
 */
class OrganisationInviteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name,
            'email' => fake()->email,
            'role' => fake()->randomElement(OrganisationRole::cases()),
            'token' => Str::uuid(),
        ];
    }
}
