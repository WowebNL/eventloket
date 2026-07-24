<?php

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Users\OrganiserUser;

test('canAccessOrganisation returns false when organisation id is null', function () {
    $user = User::factory()->create(['role' => Role::Organiser]);
    $organiser = OrganiserUser::findOrFail($user->id);

    // A zaak without a linked organisation must never be accessible, and must
    // not throw a TypeError (see Sentry EVENTLOKET-T).
    expect($organiser->canAccessOrganisation(null))->toBeFalse();
    expect($organiser->canAccessOrganisation(null, OrganisationRole::Admin))->toBeFalse();
});

test('canAccessOrganisation returns true for an attached organisation', function () {
    $user = User::factory()->create(['role' => Role::Organiser]);
    $organiser = OrganiserUser::findOrFail($user->id);

    $organisation = Organisation::factory()->create();
    $organiser->organisations()->attach($organisation, ['role' => OrganisationRole::Admin->value]);

    expect($organiser->canAccessOrganisation($organisation->id))->toBeTrue();
    expect($organiser->canAccessOrganisation($organisation->id, OrganisationRole::Admin))->toBeTrue();
});

test('canAccessOrganisation returns false for an organisation the user is not attached to', function () {
    $user = User::factory()->create(['role' => Role::Organiser]);
    $organiser = OrganiserUser::findOrFail($user->id);

    $organisation = Organisation::factory()->create();

    expect($organiser->canAccessOrganisation($organisation->id))->toBeFalse();
});
