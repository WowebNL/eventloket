<?php

use App\Enums\Role;
use App\Models\User;
use App\Policies\DefaultAdviceQuestionPolicy;
use App\Policies\LocationPolicy;
use App\Policies\MunicipalityVariablePolicy;

describe('MunicipalityVariablePolicy::create()', function () {
    beforeEach(function () {
        $this->policy = new MunicipalityVariablePolicy;
    });

    it('allows municipality admin', function () {
        $user = User::factory()->create(['role' => Role::MunicipalityAdmin]);
        expect($this->policy->create($user))->toBeTrue();
    });

    it('allows reviewer municipality admin', function () {
        $user = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
        expect($this->policy->create($user))->toBeTrue();
    });

    it('allows admin', function () {
        $user = User::factory()->create(['role' => Role::Admin]);
        expect($this->policy->create($user))->toBeTrue();
    });

    it('denies reviewer', function () {
        $user = User::factory()->create(['role' => Role::Reviewer]);
        expect($this->policy->create($user))->toBeFalse();
    });

    it('denies advisor', function () {
        $user = User::factory()->create(['role' => Role::Advisor]);
        expect($this->policy->create($user))->toBeFalse();
    });

    it('denies organiser', function () {
        $user = User::factory()->create(['role' => Role::Organiser]);
        expect($this->policy->create($user))->toBeFalse();
    });
});

describe('LocationPolicy::create()', function () {
    beforeEach(function () {
        $this->policy = new LocationPolicy;
    });

    it('allows municipality admin', function () {
        $user = User::factory()->create(['role' => Role::MunicipalityAdmin]);
        expect($this->policy->create($user))->toBeTrue();
    });

    it('allows reviewer municipality admin', function () {
        $user = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
        expect($this->policy->create($user))->toBeTrue();
    });

    it('allows admin', function () {
        $user = User::factory()->create(['role' => Role::Admin]);
        expect($this->policy->create($user))->toBeTrue();
    });

    it('denies reviewer', function () {
        $user = User::factory()->create(['role' => Role::Reviewer]);
        expect($this->policy->create($user))->toBeFalse();
    });

    it('denies advisor', function () {
        $user = User::factory()->create(['role' => Role::Advisor]);
        expect($this->policy->create($user))->toBeFalse();
    });

    it('denies organiser', function () {
        $user = User::factory()->create(['role' => Role::Organiser]);
        expect($this->policy->create($user))->toBeFalse();
    });
});

describe('DefaultAdviceQuestionPolicy::create()', function () {
    beforeEach(function () {
        $this->policy = new DefaultAdviceQuestionPolicy;
    });

    it('allows municipality admin', function () {
        $user = User::factory()->create(['role' => Role::MunicipalityAdmin]);
        expect($this->policy->create($user))->toBeTrue();
    });

    it('allows reviewer municipality admin', function () {
        $user = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
        expect($this->policy->create($user))->toBeTrue();
    });

    it('allows admin', function () {
        $user = User::factory()->create(['role' => Role::Admin]);
        expect($this->policy->create($user))->toBeTrue();
    });

    it('denies reviewer', function () {
        $user = User::factory()->create(['role' => Role::Reviewer]);
        expect($this->policy->create($user))->toBeFalse();
    });

    it('denies advisor', function () {
        $user = User::factory()->create(['role' => Role::Advisor]);
        expect($this->policy->create($user))->toBeFalse();
    });

    it('denies organiser', function () {
        $user = User::factory()->create(['role' => Role::Organiser]);
        expect($this->policy->create($user))->toBeFalse();
    });
});
