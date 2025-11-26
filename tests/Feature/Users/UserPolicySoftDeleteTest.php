<?php

use App\Enums\Role;
use App\Models\Municipality;
use App\Models\User;
use App\Policies\AdminUserPolicy;
use App\Policies\AdvisorUserPolicy;
use App\Policies\MunicipalityAdminUserPolicy;
use App\Policies\OrganiserUserPolicy;
use App\Policies\ReviewerMunicipalityAdminUserPolicy;
use App\Policies\ReviewerUserPolicy;
use App\Policies\UserPolicy;

test('admin can delete municipality admin users', function () {
    $policy = new MunicipalityAdminUserPolicy;
    $adminUser = User::factory()->create(['role' => Role::Admin]);
    $municipalityAdminUser = User::factory()->create(['role' => Role::MunicipalityAdmin]);

    expect($policy->delete($adminUser, $municipalityAdminUser))->toBeTrue();
});

test('admin can delete reviewer municipality admin users', function () {
    $policy = new ReviewerMunicipalityAdminUserPolicy;
    $adminUser = User::factory()->create(['role' => Role::Admin]);
    $reviewerMunicipalityAdminUser = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);

    expect($policy->delete($adminUser, $reviewerMunicipalityAdminUser))->toBeTrue();
});

test('admin can delete advisor users', function () {
    $policy = new AdvisorUserPolicy;
    $adminUser = User::factory()->create(['role' => Role::Admin]);
    $advisorUser = User::factory()->create(['role' => Role::Advisor]);

    expect($policy->delete($adminUser, $advisorUser))->toBeTrue();
});

test('municipality admin can delete reviewer users in their municipality', function () {
    $policy = new ReviewerUserPolicy;
    $municipality = Municipality::factory()->create();
    $municipalityAdminUser = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $reviewerUser = User::factory()->create(['role' => Role::Reviewer]);

    $municipalityAdminUser->municipalities()->attach($municipality);
    $reviewerUser->municipalities()->attach($municipality);

    expect($policy->delete($municipalityAdminUser, $reviewerUser))->toBeTrue();
});

test('municipality admin cannot delete reviewer users from other municipalities', function () {
    $policy = new ReviewerUserPolicy;
    $municipality = Municipality::factory()->create();
    $otherMunicipality = Municipality::factory()->create();
    $municipalityAdminUser = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $otherReviewerUser = User::factory()->create(['role' => Role::Reviewer]);

    $municipalityAdminUser->municipalities()->attach($municipality);
    $otherReviewerUser->municipalities()->attach($otherMunicipality);

    expect($policy->delete($municipalityAdminUser, $otherReviewerUser))->toBeFalse();
});

test('admin can restore municipality admin users', function () {
    $policy = new MunicipalityAdminUserPolicy;
    $adminUser = User::factory()->create(['role' => Role::Admin]);
    $municipalityAdminUser = User::factory()->create(['role' => Role::MunicipalityAdmin]);

    expect($policy->restore($adminUser, $municipalityAdminUser))->toBeTrue();
});

test('admin can restore reviewer municipality admin users', function () {
    $policy = new ReviewerMunicipalityAdminUserPolicy;
    $adminUser = User::factory()->create(['role' => Role::Admin]);
    $reviewerMunicipalityAdminUser = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);

    expect($policy->restore($adminUser, $reviewerMunicipalityAdminUser))->toBeTrue();
});

test('admin can restore reviewer users via generic policy', function () {
    $policy = new UserPolicy;
    $adminUser = User::factory()->create(['role' => Role::Admin]);
    $reviewerUser = User::factory()->create(['role' => Role::Reviewer]);

    expect($policy->restore($adminUser, $reviewerUser))->toBeTrue();
});

test('municipality admin can restore reviewer users in their municipality via generic policy', function () {
    $policy = new UserPolicy;
    $municipality = Municipality::factory()->create();
    $municipalityAdminUser = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $reviewerUser = User::factory()->create(['role' => Role::Reviewer]);

    $municipalityAdminUser->municipalities()->attach($municipality);
    $reviewerUser->municipalities()->attach($municipality);

    expect($policy->restore($municipalityAdminUser, $reviewerUser))->toBeTrue();
});

test('municipality admin cannot restore reviewer users from other municipalities via generic policy', function () {
    $policy = new UserPolicy;
    $municipality = Municipality::factory()->create();
    $otherMunicipality = Municipality::factory()->create();
    $municipalityAdminUser = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $otherReviewerUser = User::factory()->create(['role' => Role::Reviewer]);

    $municipalityAdminUser->municipalities()->attach($municipality);
    $otherReviewerUser->municipalities()->attach($otherMunicipality);

    expect($policy->restore($municipalityAdminUser, $otherReviewerUser))->toBeFalse();
});

test('admin can force delete municipality admin users', function () {
    $policy = new MunicipalityAdminUserPolicy;
    $adminUser = User::factory()->create(['role' => Role::Admin]);
    $municipalityAdminUser = User::factory()->create(['role' => Role::MunicipalityAdmin]);

    expect($policy->forceDelete($adminUser, $municipalityAdminUser))->toBeTrue();
});

test('admin can force delete reviewer municipality admin users', function () {
    $policy = new ReviewerMunicipalityAdminUserPolicy;
    $adminUser = User::factory()->create(['role' => Role::Admin]);
    $reviewerMunicipalityAdminUser = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);

    expect($policy->forceDelete($adminUser, $reviewerMunicipalityAdminUser))->toBeTrue();
});

test('admin can force delete reviewer users via generic policy', function () {
    $policy = new UserPolicy;
    $adminUser = User::factory()->create(['role' => Role::Admin]);
    $reviewerUser = User::factory()->create(['role' => Role::Reviewer]);

    expect($policy->forceDelete($adminUser, $reviewerUser))->toBeTrue();
});

test('admin cannot delete other admin users via admin policy', function () {
    $policy = new AdminUserPolicy;
    $adminUser = User::factory()->create(['role' => Role::Admin]);
    $otherAdmin = User::factory()->create(['role' => Role::Admin]);

    expect($policy->delete($adminUser, $otherAdmin))->toBeFalse();
});

test('admin can force delete organiser users', function () {
    $policy = new OrganiserUserPolicy;
    $adminUser = User::factory()->create(['role' => Role::Admin]);
    $organiserUser = User::factory()->create(['role' => Role::Organiser]);

    expect($policy->forceDelete($adminUser, $organiserUser))->toBeTrue();
});

test('soft delete permissions work with actual database records', function () {
    $policy = new UserPolicy;
    $adminUser = User::factory()->create(['role' => Role::Admin]);
    $municipality = Municipality::factory()->create();
    $municipalityAdminUser = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $reviewerUser = User::factory()->create(['role' => Role::Reviewer]);

    $municipalityAdminUser->municipalities()->attach($municipality);
    $reviewerUser->municipalities()->attach($municipality);

    // Test with actual soft deleted users
    $municipalityAdminUser->delete();
    $reviewerUser->delete();

    // Get fresh instances with trashed models included
    $municipalityAdminUserFresh = User::withTrashed()->find($municipalityAdminUser->id);
    $reviewerUserFresh = User::withTrashed()->find($reviewerUser->id);

    expect($municipalityAdminUserFresh)->not->toBeNull()
        ->and($reviewerUserFresh)->not->toBeNull();

    expect($policy->restore($adminUser, $municipalityAdminUserFresh))->toBeTrue()
        ->and($policy->forceDelete($adminUser, $municipalityAdminUserFresh))->toBeTrue();

    // Test that soft-deleted users cannot perform actions on other users
    // A soft-deleted municipality admin should not be able to restore other users
    expect($policy->restore($municipalityAdminUserFresh, $reviewerUserFresh))->toBeFalse();

    // But an active admin can still restore both users
    expect($policy->restore($adminUser, $reviewerUserFresh))->toBeTrue();

    // Test that when municipality admin is restored, they can perform actions again
    $municipalityAdminUserFresh->restore();
    expect($policy->restore($municipalityAdminUserFresh, $reviewerUserFresh))->toBeTrue();

    // Restore for cleanup
    $reviewerUserFresh->restore();
    expect($reviewerUserFresh->trashed())->toBeFalse();
});

test('soft-deleted users cannot perform any policy actions', function () {
    $municipalityAdminPolicy = new MunicipalityAdminUserPolicy;
    $reviewerPolicy = new ReviewerUserPolicy;
    $advisorPolicy = new AdvisorUserPolicy;

    $adminUser = User::factory()->create(['role' => Role::Admin]);
    $municipalityAdminUser = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $anotherMunicipalityAdminUser = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $reviewerUser = User::factory()->create(['role' => Role::Reviewer]);
    $anotherReviewerUser = User::factory()->create(['role' => Role::Reviewer]);
    $advisorUser = User::factory()->create(['role' => Role::Advisor]);
    $anotherAdvisorUser = User::factory()->create(['role' => Role::Advisor]);

    // Soft delete the acting users
    $municipalityAdminUser->delete();
    $advisorUser->delete();

    // Test that soft-deleted municipality admin cannot perform actions on other municipality admins
    expect($municipalityAdminPolicy->delete($municipalityAdminUser, $anotherMunicipalityAdminUser))->toBeFalse()
        ->and($municipalityAdminPolicy->restore($municipalityAdminUser, $anotherMunicipalityAdminUser))->toBeFalse()
        ->and($municipalityAdminPolicy->forceDelete($municipalityAdminUser, $anotherMunicipalityAdminUser))->toBeFalse()

        // Test that soft-deleted reviewer cannot perform actions on other reviewers
        ->and($reviewerPolicy->delete($municipalityAdminUser, $anotherReviewerUser))->toBeFalse()

        // Test that soft-deleted advisor cannot perform actions on other advisors
        ->and($advisorPolicy->delete($advisorUser, $anotherAdvisorUser))->toBeFalse()
        ->and($advisorPolicy->restore($advisorUser, $anotherAdvisorUser))->toBeFalse()
        ->and($advisorPolicy->forceDelete($advisorUser, $anotherAdvisorUser))->toBeFalse();
});
