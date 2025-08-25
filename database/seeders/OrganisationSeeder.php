<?php

namespace Database\Seeders;

use App\Enums\OrganisationRole;
use App\Enums\OrganisationType;
use App\Enums\Role;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrganisationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organisations = Organisation::factory(['type' => OrganisationType::Business])->createMany(10);

        $organiserUsers = User::factory(['role' => Role::Organiser])->createMany(10);

        foreach ($organiserUsers as $user) {
            $randomOrganisations = $organisations->shuffle()->take(rand(1, 3));

            foreach ($randomOrganisations as $organisation) {
                $organisation->users()->attach($user->id, [
                    'role' => fake()->randomElement(OrganisationRole::cases()),
                ]);
            }
        }

    }
}
