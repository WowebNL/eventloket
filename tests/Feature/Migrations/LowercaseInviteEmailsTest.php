<?php

declare(strict_types=1);

/**
 * The migration that lowercases invite addresses stored before the invite
 * models normalised them. Rows that only differ in case collide on the unique
 * indexes, so it may only delete the ones that can no longer be accepted.
 */

use App\Models\Advisory;
use App\Models\Organisation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function lowercaseInviteEmailsMigration(): Migration
{
    return require database_path('migrations/2026_10_19_120000_lowercase_invite_emails.php');
}

/**
 * Inserts past the model, so the address keeps the case it is given.
 *
 * @param  array<string, mixed>  $attributes
 */
function rawInvite(string $table, string $email, array $attributes = []): int
{
    $createdAt = $attributes['created_at'] ?? now();

    return DB::table($table)->insertGetId([
        'email' => $email,
        'token' => (string) Str::uuid(),
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
        ...$attributes,
    ]);
}

function inviteEmail(string $table, int $id): ?string
{
    return DB::table($table)->where('id', $id)->value('email');
}

/**
 * Whether the database compares the two addresses as equal. PostgreSQL
 * compares them exactly. MySQL's default collations ignore case and accents,
 * so its unique index already rejects the second address and rows that only
 * differ that way cannot exist there.
 */
function databaseTreatsAsSameAddress(string $first, string $second): bool
{
    if (DB::getDriverName() !== 'mysql') {
        return false;
    }

    return (bool) DB::scalar('select ? = ?', [$first, $second]);
}

test('it lowercases invite addresses that do not collide', function () {
    $organisation = Organisation::factory()->create();
    $advisory = Advisory::factory()->create();

    $admin = rawInvite('admin_invites', 'Mixed.Admin@Example.com');
    $municipality = rawInvite('municipality_invites', 'Mixed.Municipality@Example.com', ['role' => 'reviewer']);
    $organisationInvite = rawInvite('organisation_invites', 'Mixed.Organiser@Example.com', ['organisation_id' => $organisation->id, 'role' => 'member']);
    $advisoryInvite = rawInvite('advisory_invites', 'Mixed.Advisor@Example.com', ['advisory_id' => $advisory->id, 'role' => 'member']);

    lowercaseInviteEmailsMigration()->up();

    expect(inviteEmail('admin_invites', $admin))->toBe('mixed.admin@example.com')
        ->and(inviteEmail('municipality_invites', $municipality))->toBe('mixed.municipality@example.com')
        ->and(inviteEmail('organisation_invites', $organisationInvite))->toBe('mixed.organiser@example.com')
        ->and(inviteEmail('advisory_invites', $advisoryInvite))->toBe('mixed.advisor@example.com');
});

test('it only lowercases ASCII letters, like the application does', function () {
    $invite = rawInvite('municipality_invites', 'Émile.Ödegaard@Example.com', ['role' => 'reviewer']);

    lowercaseInviteEmailsMigration()->up();

    expect(inviteEmail('municipality_invites', $invite))
        ->toBe(strtolower('Émile.Ödegaard@Example.com'))
        ->toBe('Émile.Ödegaard@example.com');
});

test('it leaves non-ASCII letters untouched in every invite table', function () {
    $organisation = Organisation::factory()->create();
    $advisory = Advisory::factory()->create();
    $address = 'ÉLISE.Ångström@Example.ORG';

    $invites = [
        'admin_invites' => rawInvite('admin_invites', $address),
        'advisory_invites' => rawInvite('advisory_invites', $address, ['advisory_id' => $advisory->id, 'role' => 'member']),
        'municipality_invites' => rawInvite('municipality_invites', $address, ['role' => 'reviewer']),
        'organisation_invites' => rawInvite('organisation_invites', $address, ['organisation_id' => $organisation->id, 'role' => 'member']),
    ];

    lowercaseInviteEmailsMigration()->up();

    foreach ($invites as $table => $id) {
        expect(inviteEmail($table, $id))
            ->toBe(strtolower($address))
            ->toBe('Élise.Ångström@example.org')
            ->not->toBe(mb_strtolower($address));
    }
});

test('it does not treat addresses that only differ in a non-ASCII letter as colliding', function () {
    if (databaseTreatsAsSameAddress('élise.test@example.com', 'Élise.test@example.com')) {
        $this->markTestSkipped('The collation already treats these addresses as the same, so the unique index rejects the second row.');
    }

    $expiredAt = now()->subDays(config('invites.expiration_days') + 1);

    $expired = rawInvite('municipality_invites', 'élise.test@example.com', ['role' => 'reviewer', 'created_at' => $expiredAt]);
    $live = rawInvite('municipality_invites', 'Élise.Test@Example.com', ['role' => 'reviewer']);

    lowercaseInviteEmailsMigration()->up();

    expect(inviteEmail('municipality_invites', $expired))->toBe('élise.test@example.com')
        ->and(inviteEmail('municipality_invites', $live))->toBe('Élise.test@example.com');
});

test('it deletes an expired invite that collides and lowercases the live one', function () {
    if (databaseTreatsAsSameAddress('duplicate@example.com', 'Duplicate@Example.com')) {
        $this->markTestSkipped('The collation already treats these addresses as the same, so the unique index rejects the second row.');
    }

    $organisation = Organisation::factory()->create();
    $expiredAt = now()->subDays(config('invites.expiration_days') + 1);

    $expired = rawInvite('organisation_invites', 'duplicate@example.com', ['organisation_id' => $organisation->id, 'role' => 'member', 'created_at' => $expiredAt]);
    $live = rawInvite('organisation_invites', 'Duplicate@Example.com', ['organisation_id' => $organisation->id, 'role' => 'member']);

    lowercaseInviteEmailsMigration()->up();

    expect(DB::table('organisation_invites')->where('id', $expired)->exists())->toBeFalse()
        ->and(inviteEmail('organisation_invites', $live))->toBe('duplicate@example.com');
});

test('it keeps live invites that collide as they are', function () {
    if (databaseTreatsAsSameAddress('Pending@Example.com', 'pending@example.com')) {
        $this->markTestSkipped('The collation already treats these addresses as the same, so the unique index rejects the second row.');
    }

    $first = rawInvite('municipality_invites', 'Pending@Example.com', ['role' => 'reviewer']);
    $second = rawInvite('municipality_invites', 'pending@example.com', ['role' => 'reviewer']);

    lowercaseInviteEmailsMigration()->up();

    expect(inviteEmail('municipality_invites', $first))->toBe('Pending@Example.com')
        ->and(inviteEmail('municipality_invites', $second))->toBe('pending@example.com');
});

test('it only treats addresses as colliding within the same organisation', function () {
    $organisation = Organisation::factory()->create();
    $otherOrganisation = Organisation::factory()->create();
    $expiredAt = now()->subDays(config('invites.expiration_days') + 1);

    $expiredElsewhere = rawInvite('organisation_invites', 'shared@example.com', ['organisation_id' => $otherOrganisation->id, 'role' => 'member', 'created_at' => $expiredAt]);
    $mixed = rawInvite('organisation_invites', 'Shared@Example.com', ['organisation_id' => $organisation->id, 'role' => 'member']);

    lowercaseInviteEmailsMigration()->up();

    expect(DB::table('organisation_invites')->where('id', $expiredElsewhere)->exists())->toBeTrue()
        ->and(inviteEmail('organisation_invites', $mixed))->toBe('shared@example.com');
});
