<?php

use App\Enums\OrganisationRole;
use App\Enums\OrganisationType;
use App\Enums\Role;
use App\Filament\Organiser\Pages\Tenancy\RegisterOrganisation;
use App\Models\Organisation;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('organiser'));
});

test('user without organisations can create a personal organisation', function () {
    // Create a user with no organisations
    $user = User::factory()->create([
        'role' => Role::Organiser,
    ]);

    // Login as this user
    $this->actingAs($user);

    // Make sure the user has no organisations
    expect($user->organisations()->count())->toBe(0);

    $response = livewire(RegisterOrganisation::class)
        ->callAction('noOrganisation');

    // Refresh the user model to get updated relationships
    $user->refresh();

    // Assert a personal organisation was created
    expect($user->organisations()->count())->toBe(1);
    $personalOrg = $user->organisations()->first();
    expect($personalOrg->type)->toBe(OrganisationType::Personal);
    expect($personalOrg->name)->toBe('Mijn omgeving');

    // Assert user is attached with Admin role
    expect($user->organisations()
        ->wherePivot('role', OrganisationRole::Admin->value)
        ->exists()
    )->toBeTrue();

    // Assert the user was redirected to the organisation page
    $response->assertRedirect(Filament::getUrl($personalOrg));
});

test('user with existing organisations cannot create a personal organisation', function () {
    // Create a user
    $user = User::factory()->create([
        'role' => Role::Organiser,
    ]);

    // Create an existing organisation for this user
    $existingOrg = Organisation::factory()->create([
        'type' => OrganisationType::Personal,
        'name' => 'Existing Personal Org',
    ]);

    // Attach user to organisation
    $existingOrg->users()->attach($user, [
        'role' => OrganisationRole::Admin->value,
    ]);

    // Login as this user
    $this->actingAs($user);

    // Verify the user has an organisation
    expect($user->organisations()->count())->toBe(1);

    // The noOrganisationAction should not be visible
    $livewire = livewire(RegisterOrganisation::class);

    // Test that the action is not visible
    expect($livewire->instance()->noOrganisationAction()->isVisible())->toBeFalse();

    // Test that calling the action fails
    try {
        $livewire->callAction('noOrganisation');
        $actionExecuted = true;
    } catch (\Exception $e) {
        $actionExecuted = false;
    }

    expect($actionExecuted)->toBeFalse();

    // Make sure no additional organisation was created
    $user->refresh();
    expect($user->organisations()->count())->toBe(1);
});

test('personal organisation is automatically created only once', function () {
    // Create two users
    $user1 = User::factory()->create(['role' => Role::Organiser]);
    $user2 = User::factory()->create(['role' => Role::Organiser]);

    // Login as first user
    $this->actingAs($user1);

    // Create personal org for first user
    livewire(RegisterOrganisation::class)
        ->callAction('noOrganisation');

    // Switch to second user
    Auth::logout();
    $this->actingAs($user2);

    // Create personal org for second user
    livewire(RegisterOrganisation::class)
        ->callAction('noOrganisation');

    // Refresh users
    $user1->refresh();
    $user2->refresh();

    // Each user should have exactly one personal organisation
    expect($user1->organisations()->count())->toBe(1);
    expect($user2->organisations()->count())->toBe(1);

    // Both personal orgs should be separate
    $personalOrg1 = $user1->organisations()->first();
    $personalOrg2 = $user2->organisations()->first();

    expect($personalOrg1->id)->not->toBe($personalOrg2->id);

    // But both should be personal type
    expect($personalOrg1->type)->toBe(OrganisationType::Personal);
    expect($personalOrg2->type)->toBe(OrganisationType::Personal);
});
