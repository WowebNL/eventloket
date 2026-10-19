<?php

/**
 * Every form that stores a user's email address has to reject an address that
 * an existing account already uses in different case. User stores addresses in
 * lowercase and PostgreSQL compares case sensitively, so a plain unique rule
 * lets such an address through and the save then fails on the unique index.
 */

use App\Enums\AdvisoryRole;
use App\Enums\OrganisationRole;
use App\Enums\OrganisationType;
use App\Enums\Role;
use App\Filament\Admin\Resources\Organisations\Pages\EditOrganisation;
use App\Filament\Admin\Resources\Organisations\RelationManagers\UsersRelationManager as OrganisationUsersRelationManager;
use App\Filament\Municipality\Clusters\Settings\Resources\AdvisoryResource\Pages\EditAdvisory;
use App\Filament\Municipality\Clusters\Settings\Resources\AdvisoryResource\RelationManagers\UsersRelationManager as AdvisoryUsersRelationManager;
use App\Filament\Organiser\Pages\Register;
use App\Filament\Shared\Pages\EditProfile;
use App\Livewire\AcceptInvites\AcceptOrganisationInvite;
use App\Models\Advisory;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Rules\UniqueUserEmail;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->existing = User::factory()->create([
        'email' => 'existing.account@example.com',
        'role' => Role::Organiser,
    ]);
});

test('organiser registration rejects an existing address in different case', function () {
    Filament::setCurrentPanel(Filament::getPanel('organiser'));

    livewire(Register::class)
        ->fillForm([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'Existing.Account@Example.com',
            'phone' => '0612345678',
            'password' => 'Password123!',
            'passwordConfirmation' => 'Password123!',
        ])
        ->call('register')
        ->assertHasFormErrors(['email' => UniqueUserEmail::class]);

    expect(User::count())->toBe(1);
});

test('organiser registration treats the address of a soft deleted account as taken', function () {
    Filament::setCurrentPanel(Filament::getPanel('organiser'));

    User::factory()->create([
        'email' => 'deleted.account@example.com',
        'role' => Role::Organiser,
    ])->delete();

    livewire(Register::class)
        ->fillForm([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'Deleted.Account@Example.com',
            'phone' => '0612345678',
            'password' => 'Password123!',
            'passwordConfirmation' => 'Password123!',
        ])
        ->call('register')
        ->assertHasFormErrors(['email' => UniqueUserEmail::class]);

    expect(User::withTrashed()->count())->toBe(2);
});

test('own profile rejects changing the address to another account\'s address in different case', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $user = User::factory()->create([
        'email' => 'profile.owner@example.com',
        'role' => Role::Admin,
    ]);
    $this->actingAs($user);

    livewire(EditProfile::class)
        ->fillForm(['email' => 'EXISTING.ACCOUNT@example.com'])
        ->call('save')
        ->assertHasFormErrors(['email' => UniqueUserEmail::class]);

    expect($user->refresh()->email)->toBe('profile.owner@example.com');
});

test('own profile accepts the own address in different case', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $user = User::factory()->create([
        'email' => 'profile.owner@example.com',
        'role' => Role::Admin,
    ]);
    $this->actingAs($user);

    livewire(EditProfile::class)
        ->fillForm(['email' => 'Profile.Owner@Example.com'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->email)->toBe('profile.owner@example.com');
});

test('accepting an invite stored in different case rejects an address that already has an account', function () {
    Filament::setCurrentPanel(Filament::getPanel('organiser'));

    $organisation = Organisation::factory()->create();

    // Written past the model, the way invites were stored before the invite
    // models lowercased their address.
    $token = (string) Str::uuid();
    DB::table('organisation_invites')->insert([
        'organisation_id' => $organisation->id,
        'email' => 'Existing.Account@Example.com',
        'role' => OrganisationRole::Member->value,
        'token' => $token,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    livewire(AcceptOrganisationInvite::class, ['token' => $token])
        ->fillForm([
            'name' => 'New User',
            'phone' => '0612345678',
            'password' => 'Password123!',
            'passwordConfirmation' => 'Password123!',
        ])
        ->call('create')
        ->assertHasFormErrors(['email' => UniqueUserEmail::class]);

    expect(User::count())->toBe(1);
});

test('the organisation users form rejects another account\'s address in different case', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => Role::Admin]));

    $organisation = Organisation::factory(['type' => OrganisationType::Business])->create();
    $member = User::factory()->create([
        'email' => 'organisation.member@example.com',
        'role' => Role::Organiser,
    ]);
    $organisation->users()->attach($member, ['role' => OrganisationRole::Member->value]);

    livewire(OrganisationUsersRelationManager::class, [
        'ownerRecord' => $organisation,
        'pageClass' => EditOrganisation::class,
    ])
        ->callTableAction('edit', $member, [
            'email' => 'Existing.Account@Example.com',
        ])
        ->assertHasTableActionErrors(['email' => UniqueUserEmail::class]);

    expect($member->refresh()->email)->toBe('organisation.member@example.com');
});

test('the advisory users form rejects another account\'s address in different case', function () {
    Filament::setCurrentPanel(Filament::getPanel('municipality'));

    // Only admins pass AdvisorUserPolicy::viewAny for this relation manager
    $this->actingAs(User::factory()->create(['role' => Role::Admin]));
    $municipality = Municipality::factory()->create();
    Filament::setTenant($municipality);

    $advisory = Advisory::factory()->create();
    $advisory->municipalities()->syncWithoutDetaching([$municipality->id]);
    $advisor = User::factory()->create([
        'email' => 'advisory.member@example.com',
        'role' => Role::Advisor,
    ]);
    $advisory->users()->attach($advisor, ['role' => AdvisoryRole::Member]);

    livewire(AdvisoryUsersRelationManager::class, [
        'ownerRecord' => $advisory,
        'pageClass' => EditAdvisory::class,
    ])
        ->callTableAction('edit', $advisor, [
            'email' => 'Existing.Account@Example.com',
        ])
        ->assertHasTableActionErrors(['email' => UniqueUserEmail::class]);

    expect($advisor->refresh()->email)->toBe('advisory.member@example.com');
});

test('the advisory users form accepts the edited user\'s own address in different case', function () {
    Filament::setCurrentPanel(Filament::getPanel('municipality'));

    $this->actingAs(User::factory()->create(['role' => Role::Admin]));
    $municipality = Municipality::factory()->create();
    Filament::setTenant($municipality);

    $advisory = Advisory::factory()->create();
    $advisory->municipalities()->syncWithoutDetaching([$municipality->id]);
    $advisor = User::factory()->create([
        'email' => 'advisory.member@example.com',
        'role' => Role::Advisor,
    ]);
    $advisory->users()->attach($advisor, ['role' => AdvisoryRole::Member]);

    livewire(AdvisoryUsersRelationManager::class, [
        'ownerRecord' => $advisory,
        'pageClass' => EditAdvisory::class,
    ])
        ->callTableAction('edit', $advisor, [
            'email' => 'Advisory.Member@Example.com',
        ])
        ->assertHasNoTableActionErrors();

    expect($advisor->refresh()->email)->toBe('advisory.member@example.com');
});
