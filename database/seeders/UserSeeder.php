<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Michel Verhoeven',
            'email' => 'michel@woweb.nl',
            'role' => Role::Admin,
            'app_authentication_secret' => null,
            'app_authentication_recovery_codes' => null,
        ]);

        User::factory()->create([
            'name' => 'Lorenso D\'Agostino',
            'email' => 'lorenso@dagostino.digital',
            'role' => Role::Admin,
            'app_authentication_secret' => null,
            'app_authentication_recovery_codes' => null,
        ]);
    }
}
