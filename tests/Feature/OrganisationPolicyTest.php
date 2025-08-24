<?php

use App\Enums\OrganisationRole;
use App\Enums\OrganisationType;
use App\Enums\Role;
use App\Models\Organisation;
use App\Models\User;
use App\Policies\OrganisationPolicy;

// Test viewing all organisations
test('allows any user to view all organisations', function () {
    $user = User::factory()->create(['role' => Role::Organiser]);
    $policy = new OrganisationPolicy;

    expect($policy->viewAny($user))->toBeTrue();
});

// Test viewing specific organisations
test('allows users to view organisations they have access to', function () {
    $user = User::factory()->create(['role' => Role::Organiser]);
    $organisation = Organisation::factory()->create();

    // User has no access initially
    $policy = new OrganisationPolicy;
    expect($policy->view($user, $organisation))->toBeFalse();

    // Grant access and test again
    $organisation->users()->attach($user, ['role' => OrganisationRole::Member->value]);
    expect($policy->view($user, $organisation))->toBeTrue();
});

// Test organisation creation
test('allows any user to create organisations', function () {
    $user = User::factory()->create(['role' => Role::Organiser]);
    $policy = new OrganisationPolicy;

    expect($policy->create($user))->toBeTrue();
});

// Test personal organisation update restrictions
test('prevents updating personal organisations', function () {
    $user = User::factory()->create(['role' => Role::Organiser]);
    $personalOrg = Organisation::factory()->create([
        'type' => OrganisationType::Personal,
    ]);
    $personalOrg->users()->attach($user, ['role' => OrganisationRole::Admin->value]);

    $policy = new OrganisationPolicy;
    expect($policy->update($user, $personalOrg))->toBeFalse();
});

// Test business organisation update permissions
test('allows admins to update business organisations', function () {
    $user = User::factory()->create(['role' => Role::Organiser]);
    $businessOrg = Organisation::factory()->create([
        'type' => OrganisationType::Business,
    ]);

    // User with member role cannot update
    $businessOrg->users()->attach($user, ['role' => OrganisationRole::Member->value]);
    $policy = new OrganisationPolicy;
    expect($policy->update($user, $businessOrg))->toBeFalse();

    // Detach and reattach with admin role
    $businessOrg->users()->detach($user);
    $businessOrg->users()->attach($user, ['role' => OrganisationRole::Admin->value]);
    expect($policy->update($user, $businessOrg))->toBeTrue();
});

// Test personal organisation deletion restrictions
test('prevents deleting personal organisations', function () {
    $user = User::factory()->create(['role' => Role::Organiser]);
    $personalOrg = Organisation::factory()->create([
        'type' => OrganisationType::Personal,
    ]);
    $personalOrg->users()->attach($user, ['role' => OrganisationRole::Admin->value]);

    $policy = new OrganisationPolicy;
    expect($policy->delete($user, $personalOrg))->toBeFalse();
});

// Test business organisation deletion permissions
test('allows admins to delete business organisations', function () {
    $user = User::factory()->create(['role' => Role::Organiser]);
    $businessOrg = Organisation::factory()->create([
        'type' => OrganisationType::Business,
    ]);

    // User with member role cannot delete
    $businessOrg->users()->attach($user, ['role' => OrganisationRole::Member->value]);
    $policy = new OrganisationPolicy;
    expect($policy->delete($user, $businessOrg))->toBeFalse();

    // Detach and reattach with admin role
    $businessOrg->users()->detach($user);
    $businessOrg->users()->attach($user, ['role' => OrganisationRole::Admin->value]);
    expect($policy->delete($user, $businessOrg))->toBeTrue();
});

// Test restore and forceDelete permissions
test('has consistent permissions for restore and forceDelete', function () {
    $user = User::factory()->create(['role' => Role::Organiser]);
    $policy = new OrganisationPolicy;

    // Test with personal org
    $personalOrg = Organisation::factory()->create([
        'type' => OrganisationType::Personal,
    ]);
    $personalOrg->users()->attach($user, ['role' => OrganisationRole::Admin->value]);

    expect($policy->restore($user, $personalOrg))->toBeFalse();
    expect($policy->forceDelete($user, $personalOrg))->toBeFalse();

    // Test with business org - non-admin
    $businessOrg = Organisation::factory()->create([
        'type' => OrganisationType::Business,
    ]);
    $businessOrg->users()->attach($user, ['role' => OrganisationRole::Member->value]);

    expect($policy->restore($user, $businessOrg))->toBeFalse();
    expect($policy->forceDelete($user, $businessOrg))->toBeFalse();

    // Test with business org - admin
    $businessOrg->users()->detach($user);
    $businessOrg->users()->attach($user, ['role' => OrganisationRole::Admin->value]);

    expect($policy->restore($user, $businessOrg))->toBeTrue();
    expect($policy->forceDelete($user, $businessOrg))->toBeTrue();
});
