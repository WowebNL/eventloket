<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\MunicipalityInvite;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MunicipalityInvite>
 */
class MunicipalityInviteFactory extends Factory
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
            'role' => fake()->randomElement([Role::Reviewer, Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin]),
            'token' => Str::uuid(),
        ];
    }
}
