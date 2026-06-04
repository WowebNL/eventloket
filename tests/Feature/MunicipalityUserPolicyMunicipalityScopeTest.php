<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Municipality;
use App\Models\User;
use App\Policies\MunicipalityAdminUserPolicy;
use App\Policies\ReviewerMunicipalityAdminUserPolicy;
use App\Policies\ReviewerUserPolicy;

beforeEach(function () {
    $this->municipality = Municipality::factory()->create();
    $this->otherMunicipality = Municipality::factory()->create();
});

// ---------------------------------------------------------------------------
// ReviewerUserPolicy::update()
// ---------------------------------------------------------------------------

test('municipality admin in same municipality can update reviewer', function () {
    $actor = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $actor->municipalities()->attach($this->municipality);

    $target = User::factory()->create(['role' => Role::Reviewer]);
    $target->municipalities()->attach($this->municipality);

    expect((new ReviewerUserPolicy)->update($actor, $target))->toBeTrue();
});

test('municipality admin in different municipality cannot update reviewer', function () {
    $actor = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $actor->municipalities()->attach($this->otherMunicipality);

    $target = User::factory()->create(['role' => Role::Reviewer]);
    $target->municipalities()->attach($this->municipality);

    expect((new ReviewerUserPolicy)->update($actor, $target))->toBeFalse();
});

test('reviewer municipality admin in same municipality can update reviewer', function () {
    $actor = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
    $actor->municipalities()->attach($this->municipality);

    $target = User::factory()->create(['role' => Role::Reviewer]);
    $target->municipalities()->attach($this->municipality);

    expect((new ReviewerUserPolicy)->update($actor, $target))->toBeTrue();
});

test('reviewer municipality admin in different municipality cannot update reviewer', function () {
    $actor = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
    $actor->municipalities()->attach($this->otherMunicipality);

    $target = User::factory()->create(['role' => Role::Reviewer]);
    $target->municipalities()->attach($this->municipality);

    expect((new ReviewerUserPolicy)->update($actor, $target))->toBeFalse();
});

test('admin can always update reviewer regardless of municipality', function () {
    $actor = User::factory()->create(['role' => Role::Admin]);

    $target = User::factory()->create(['role' => Role::Reviewer]);
    $target->municipalities()->attach($this->municipality);

    expect((new ReviewerUserPolicy)->update($actor, $target))->toBeTrue();
});

// ---------------------------------------------------------------------------
// MunicipalityAdminUserPolicy::update / delete / restore
// ---------------------------------------------------------------------------

test('municipality admin in same municipality can update municipality admin', function () {
    $actor = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $actor->municipalities()->attach($this->municipality);

    $target = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $target->municipalities()->attach($this->municipality);

    expect((new MunicipalityAdminUserPolicy)->update($actor, $target))->toBeTrue();
});

test('municipality admin in different municipality cannot update municipality admin', function () {
    $actor = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $actor->municipalities()->attach($this->otherMunicipality);

    $target = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $target->municipalities()->attach($this->municipality);

    expect((new MunicipalityAdminUserPolicy)->update($actor, $target))->toBeFalse();
});

test('municipality admin in same municipality can delete municipality admin', function () {
    $actor = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $actor->municipalities()->attach($this->municipality);

    $target = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $target->municipalities()->attach($this->municipality);

    expect((new MunicipalityAdminUserPolicy)->delete($actor, $target))->toBeTrue();
});

test('municipality admin in different municipality cannot delete municipality admin', function () {
    $actor = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $actor->municipalities()->attach($this->otherMunicipality);

    $target = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $target->municipalities()->attach($this->municipality);

    expect((new MunicipalityAdminUserPolicy)->delete($actor, $target))->toBeFalse();
});

test('municipality admin in same municipality can restore municipality admin', function () {
    $actor = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $actor->municipalities()->attach($this->municipality);

    $target = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $target->municipalities()->attach($this->municipality);

    expect((new MunicipalityAdminUserPolicy)->restore($actor, $target))->toBeTrue();
});

test('municipality admin in different municipality cannot restore municipality admin', function () {
    $actor = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $actor->municipalities()->attach($this->otherMunicipality);

    $target = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $target->municipalities()->attach($this->municipality);

    expect((new MunicipalityAdminUserPolicy)->restore($actor, $target))->toBeFalse();
});

test('admin can always update/delete/restore municipality admin regardless of municipality', function () {
    $actor = User::factory()->create(['role' => Role::Admin]);

    $target = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $target->municipalities()->attach($this->municipality);

    $policy = new MunicipalityAdminUserPolicy;

    expect($policy->update($actor, $target))->toBeTrue()
        ->and($policy->delete($actor, $target))->toBeTrue()
        ->and($policy->restore($actor, $target))->toBeTrue();
});

// ---------------------------------------------------------------------------
// ReviewerMunicipalityAdminUserPolicy::update / delete / restore
// ---------------------------------------------------------------------------

test('municipality admin in same municipality can update reviewer municipality admin', function () {
    $actor = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $actor->municipalities()->attach($this->municipality);

    $target = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
    $target->municipalities()->attach($this->municipality);

    expect((new ReviewerMunicipalityAdminUserPolicy)->update($actor, $target))->toBeTrue();
});

test('municipality admin in different municipality cannot update reviewer municipality admin', function () {
    $actor = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $actor->municipalities()->attach($this->otherMunicipality);

    $target = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
    $target->municipalities()->attach($this->municipality);

    expect((new ReviewerMunicipalityAdminUserPolicy)->update($actor, $target))->toBeFalse();
});

test('municipality admin in same municipality can delete reviewer municipality admin', function () {
    $actor = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $actor->municipalities()->attach($this->municipality);

    $target = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
    $target->municipalities()->attach($this->municipality);

    expect((new ReviewerMunicipalityAdminUserPolicy)->delete($actor, $target))->toBeTrue();
});

test('municipality admin in different municipality cannot delete reviewer municipality admin', function () {
    $actor = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $actor->municipalities()->attach($this->otherMunicipality);

    $target = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
    $target->municipalities()->attach($this->municipality);

    expect((new ReviewerMunicipalityAdminUserPolicy)->delete($actor, $target))->toBeFalse();
});

test('municipality admin in same municipality can restore reviewer municipality admin', function () {
    $actor = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $actor->municipalities()->attach($this->municipality);

    $target = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
    $target->municipalities()->attach($this->municipality);

    expect((new ReviewerMunicipalityAdminUserPolicy)->restore($actor, $target))->toBeTrue();
});

test('municipality admin in different municipality cannot restore reviewer municipality admin', function () {
    $actor = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $actor->municipalities()->attach($this->otherMunicipality);

    $target = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
    $target->municipalities()->attach($this->municipality);

    expect((new ReviewerMunicipalityAdminUserPolicy)->restore($actor, $target))->toBeFalse();
});

test('admin can always update/delete/restore reviewer municipality admin regardless of municipality', function () {
    $actor = User::factory()->create(['role' => Role::Admin]);

    $target = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
    $target->municipalities()->attach($this->municipality);

    $policy = new ReviewerMunicipalityAdminUserPolicy;

    expect($policy->update($actor, $target))->toBeTrue()
        ->and($policy->delete($actor, $target))->toBeTrue()
        ->and($policy->restore($actor, $target))->toBeTrue();
});
