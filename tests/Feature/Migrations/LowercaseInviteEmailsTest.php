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

test('it deletes an expired invite that collides and lowercases the live one', function () {
    $organisation = Organisation::factory()->create();
    $expiredAt = now()->subDays(config('invites.expiration_days') + 1);

    $expired = rawInvite('organisation_invites', 'duplicate@example.com', ['organisation_id' => $organisation->id, 'role' => 'member', 'created_at' => $expiredAt]);
    $live = rawInvite('organisation_invites', 'Duplicate@Example.com', ['organisation_id' => $organisation->id, 'role' => 'member']);

    lowercaseInviteEmailsMigration()->up();

    expect(DB::table('organisation_invites')->where('id', $expired)->exists())->toBeFalse()
        ->and(inviteEmail('organisation_invites', $live))->toBe('duplicate@example.com');
});

test('it keeps live invites that collide as they are', function () {
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
