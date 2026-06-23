<?php

declare(strict_types=1);

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Models\Organisation;
use App\Models\User;
use App\Policies\OrganiserUserPolicy;

beforeEach(function () {
    $this->policy = new OrganiserUserPolicy;
});

describe('viewAny', function () {
    it('allows admin to view any organiser users', function () {
        $admin = User::factory()->create(['role' => Role::Admin]);

        expect($this->policy->viewAny($admin))->toBeTrue();
    });

    it('allows organiser with admin role in an organisation to view any organiser users', function () {
        $organiser = User::factory()->create(['role' => Role::Organiser]);
        $organisation = Organisation::factory()->create();
        $organisation->users()->attach($organiser, ['role' => OrganisationRole::Admin]);

        expect($this->policy->viewAny($organiser))->toBeTrue();
    });

    it('denies organiser with only member role from viewing any organiser users', function () {
        $organiser = User::factory()->create(['role' => Role::Organiser]);
        $organisation = Organisation::factory()->create();
        $organisation->users()->attach($organiser, ['role' => OrganisationRole::Member]);

        expect($this->policy->viewAny($organiser))->toBeFalse();
    });

    it('denies organiser without any organisation from viewing any organiser users', function () {
        $organiser = User::factory()->create(['role' => Role::Organiser]);

        expect($this->policy->viewAny($organiser))->toBeFalse();
    });

    it('denies reviewer from viewing any organiser users', function () {
        $reviewer = User::factory()->create(['role' => Role::Reviewer]);

        expect($this->policy->viewAny($reviewer))->toBeFalse();
    });

    it('denies advisor from viewing any organiser users', function () {
        $advisor = User::factory()->create(['role' => Role::Advisor]);

        expect($this->policy->viewAny($advisor))->toBeFalse();
    });
});
