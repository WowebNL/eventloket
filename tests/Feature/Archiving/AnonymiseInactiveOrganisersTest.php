<?php

use App\Enums\Role;
use App\Models\Municipality;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

function makeOrganiserInactiveSince(User $organiser, int $months): void
{
    DB::table('users')->where('id', $organiser->id)->update([
        'updated_at' => now()->subMonths($months),
    ]);
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
