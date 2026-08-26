<?php

use App\Enums\Role;
use App\Models\Municipality;
use App\Models\User;
use App\Models\Users\OrganiserUser;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Carbon\CarbonInterface;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Writes a login entry the way the LogLogin listener does. The integration
 * between the listener and this format is asserted separately below.
 */
function recordLogin(User $user, CarbonInterface $at): void
{
    DB::table('activity_log')->insert([
        'log_name' => 'auth',
        'event' => 'login',
        'description' => 'login',
        'causer_type' => User::class,
        'causer_id' => $user->id,
        'created_at' => $at,
        'updated_at' => $at,
    ]);
}

/**
 * Every activity log entry that points at this user, as either the subject of
 * the entry or its causer.
 */
function activityEntriesFor(User $user): int
{
    $types = [User::class, OrganiserUser::class];

    return DB::table('activity_log')
        ->where(fn ($query) => $query
            ->where(fn ($sub) => $sub->whereIn('subject_type', $types)->where('subject_id', (string) $user->id))
            ->orWhere(fn ($sub) => $sub->whereIn('causer_type', $types)->where('causer_id', (string) $user->id)))
        ->count();
}

/**
 * An account created long ago whose last login was $months ago.
 */
function makeOrganiserInactiveSince(User $organiser, int $months): void
{
    DB::table('users')->where('id', $organiser->id)->update([
        'created_at' => now()->subMonths($months + 1),
        'updated_at' => now()->subMonths($months + 1),
    ]);

    recordLogin($organiser, now()->subMonths($months));
}

test('anonymises inactive organisers without zaken', function () {
    $organiser = User::factory()->create(['role' => Role::Organiser, 'phone' => '0612345678']);
    makeOrganiserInactiveSince($organiser, 30);

    $originalEmail = $organiser->email;

    $this->artisan('archiving:anonymise-inactive-organisers')->assertSuccessful();

    $organiser->refresh();

    expect($organiser->anonymised_at)->not->toBeNull()
        ->and($organiser->email)->not->toBe($originalEmail)
        ->and($organiser->name)->toBe('Geanonimiseerde gebruiker')
        ->and($organiser->first_name)->toBeNull()
        ->and($organiser->last_name)->toBeNull()
        ->and($organiser->phone)->toBeNull();
});

test('does not anonymise organisers that still have zaken somewhere', function () {
    $organiser = User::factory()->create(['role' => Role::Organiser]);
    makeOrganiserInactiveSince($organiser, 30);

    $municipality = Municipality::factory()->create();
    $zaaktype = Zaaktype::factory()->create(['municipality_id' => $municipality->id]);
    Model::withoutEvents(fn () => Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'organiser_user_id' => $organiser->id,
    ]));

    $this->artisan('archiving:anonymise-inactive-organisers')->assertSuccessful();

    expect($organiser->refresh()->anonymised_at)->toBeNull();
});

test('does not anonymise recently active organisers', function () {
    $organiser = User::factory()->create(['role' => Role::Organiser]);
    makeOrganiserInactiveSince($organiser, 3);

    $this->artisan('archiving:anonymise-inactive-organisers')->assertSuccessful();

    expect($organiser->refresh()->anonymised_at)->toBeNull();
});

test('an organiser who still logs in is kept, even on a long dormant record', function () {
    $organiser = User::factory()->create(['role' => Role::Organiser]);

    // Old account whose row has not been written to in years, but who logged
    // in last week. Judging by the users row alone this would be wiped.
    DB::table('users')->where('id', $organiser->id)->update([
        'created_at' => now()->subYears(4),
        'updated_at' => now()->subYears(4),
    ]);

    recordLogin($organiser, now()->subYears(4));
    recordLogin($organiser, now()->subWeek());

    $this->artisan('archiving:anonymise-inactive-organisers')->assertSuccessful();

    expect($organiser->refresh()->anonymised_at)->toBeNull();
});

test('an account that never logged in ages out on its creation date', function () {
    $organiser = User::factory()->create(['role' => Role::Organiser]);

    DB::table('users')->where('id', $organiser->id)->update([
        'created_at' => now()->subYears(3),
        'updated_at' => now()->subYears(3),
    ]);

    $this->artisan('archiving:anonymise-inactive-organisers')->assertSuccessful();

    expect($organiser->refresh()->anonymised_at)->not->toBeNull();
});

test('a recently created account that never logged in is kept', function () {
    $organiser = User::factory()->create(['role' => Role::Organiser]);

    $this->artisan('archiving:anonymise-inactive-organisers')->assertSuccessful();

    expect($organiser->refresh()->anonymised_at)->toBeNull();
});

test('a login by another user does not keep a dormant organiser alive', function () {
    $dormant = User::factory()->create(['role' => Role::Organiser]);
    makeOrganiserInactiveSince($dormant, 30);

    $active = User::factory()->create(['role' => Role::Organiser]);
    recordLogin($active, now()->subDay());

    $this->artisan('archiving:anonymise-inactive-organisers')->assertSuccessful();

    expect($dormant->refresh()->anonymised_at)->not->toBeNull()
        ->and($active->refresh()->anonymised_at)->toBeNull();
});

test('the login listener writes an entry the command can read', function () {
    $organiser = User::factory()->create(['role' => Role::Organiser]);

    $this->actingAs($organiser);
    event(new Login('web', $organiser, false));

    $entry = DB::table('activity_log')
        ->where('log_name', 'auth')
        ->where('event', 'login')
        ->where('causer_type', User::class)
        ->where('causer_id', $organiser->id)
        ->first();

    expect($entry)->not->toBeNull();

    // And that entry is enough to save an otherwise dormant account.
    DB::table('users')->where('id', $organiser->id)->update([
        'created_at' => now()->subYears(4),
        'updated_at' => now()->subYears(4),
    ]);

    $this->artisan('archiving:anonymise-inactive-organisers')->assertSuccessful();

    expect($organiser->refresh()->anonymised_at)->toBeNull();
});

test('does not anonymise other user roles', function () {
    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    makeOrganiserInactiveSince($reviewer, 30);

    $this->artisan('archiving:anonymise-inactive-organisers')->assertSuccessful();

    expect($reviewer->refresh()->anonymised_at)->toBeNull();
});

test('dry run does not change anything', function () {
    $organiser = User::factory()->create(['role' => Role::Organiser]);
    makeOrganiserInactiveSince($organiser, 30);

    $this->artisan('archiving:anonymise-inactive-organisers --dry-run')->assertSuccessful();

    expect($organiser->refresh()->anonymised_at)->toBeNull();
});

test('the log entries of the account are removed with it', function () {
    $organiser = User::factory()->create(['role' => Role::Organiser, 'phone' => '0612345678']);
    makeOrganiserInactiveSince($organiser, 30);

    // A login (ip and user agent) and a profile change (old name and e-mail)
    // both leave personal data behind in the activity log.
    $organiser->update(['first_name' => 'Nieuwe voornaam']);

    expect(activityEntriesFor($organiser))->toBeGreaterThan(0);

    $this->artisan('archiving:anonymise-inactive-organisers')->assertSuccessful();

    expect($organiser->refresh()->anonymised_at)->not->toBeNull()
        ->and(activityEntriesFor($organiser))->toBe(0);
});

test('anonymising does not write the old values back into the activity log', function () {
    $organiser = User::factory()->create(['role' => Role::Organiser, 'phone' => '0612345678']);
    makeOrganiserInactiveSince($organiser, 30);

    $originalEmail = $organiser->email;

    $this->artisan('archiving:anonymise-inactive-organisers')->assertSuccessful();

    $properties = DB::table('activity_log')->pluck('properties')->implode(' ');

    expect($properties)->not->toContain($originalEmail)
        ->and($properties)->not->toContain('0612345678');
});

test('the log entries of other users are left alone', function () {
    $organiser = User::factory()->create(['role' => Role::Organiser]);
    makeOrganiserInactiveSince($organiser, 30);

    $other = User::factory()->create(['role' => Role::Organiser]);
    recordLogin($other, now()->subDay());

    $this->artisan('archiving:anonymise-inactive-organisers')->assertSuccessful();

    expect(activityEntriesFor($other))->toBeGreaterThan(0);
});

test('the command refuses to run when logins are pruned before accounts age out', function () {
    config(['activitylog.delete_records_older_than_days' => 365]);

    $organiser = User::factory()->create(['role' => Role::Organiser]);
    makeOrganiserInactiveSince($organiser, 30);

    $this->artisan('archiving:anonymise-inactive-organisers')->assertFailed();

    expect($organiser->refresh()->anonymised_at)->toBeNull();
});

test('the configured retention outlives the inactivity window', function () {
    expect((int) config('activitylog.delete_records_older_than_days'))
        ->toBeGreaterThan((int) config('archiving.organiser_inactivity_months') * 30);
});
