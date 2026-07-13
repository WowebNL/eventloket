<?php

namespace Database\Seeders;

use App\Enums\OrganisationRole;
use App\Enums\OrganisationType;
use App\Enums\Role;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Deterministic organiser account used by the Playwright walkthrough tests
 * (auth.setup.mjs logs in as noah.degraaf@example.net / password). The regular
 * OrganisationSeeder creates organisers with faker e-mails, so this account is
 * not guaranteed to exist after a normal seed. This seeder is idempotent and
 * safe to run repeatedly.
 */
class PlaywrightUserSeeder extends Seeder
{
    public const EMAIL = 'noah.degraaf@example.net';

    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'Noah de Graaf',
                'role' => Role::Organiser,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                // No two-factor secret so the Playwright login is a single step.
                'app_authentication_secret' => null,
                'app_authentication_recovery_codes' => null,
            ],
        );

        $organisation = Organisation::where('type', OrganisationType::Business)->first()
            ?? Organisation::factory()->create(['type' => OrganisationType::Business]);

        if (! $organisation->users()->whereKey($user->id)->exists()) {
            $organisation->users()->attach($user->id, ['role' => OrganisationRole::Admin]);
        }
    }
}
