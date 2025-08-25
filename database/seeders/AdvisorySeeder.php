<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Advisory;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdvisorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $advisories = [
            'Brandweer',
            'Politie',
            'Milieudienst',
            'Verkeerscoördinatie',
            'Evenementencoördinator',
        ];

        foreach ($advisories as $name) {
            $advisory = Advisory::factory()->create(['name' => $name]);

            $advisorUsers = User::factory(['role' => Role::Advisor])->createMany(rand(1, 3));

            foreach ($advisorUsers as $advisorUser) {
                $advisory->users()->attach($advisorUser->id);
            }
        }
    }
}
