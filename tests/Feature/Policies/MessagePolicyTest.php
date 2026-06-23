<?php

use App\Enums\Role;
use App\Models\User;
use App\Policies\MessagePolicy;

beforeEach(function () {
    $this->policy = new MessagePolicy;
});

describe('create', function () {
    it('allows reviewer to create messages', function () {
        $user = User::factory()->create(['role' => Role::Reviewer]);

        expect($this->policy->create($user))->toBeTrue();
    });

    it('allows municipality admin to create messages', function () {
        $user = User::factory()->create(['role' => Role::MunicipalityAdmin]);

        expect($this->policy->create($user))->toBeTrue();
    });

    it('allows reviewer municipality admin to create messages', function () {
        $user = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);

        expect($this->policy->create($user))->toBeTrue();
    });

    it('allows advisor to create messages', function () {
        $user = User::factory()->create(['role' => Role::Advisor]);

        expect($this->policy->create($user))->toBeTrue();
    });

    it('allows organiser to create messages', function () {
        $user = User::factory()->create(['role' => Role::Organiser]);

        expect($this->policy->create($user))->toBeTrue();
    });

    it('denies admin from creating messages', function () {
        $user = User::factory()->create(['role' => Role::Admin]);

        expect($this->policy->create($user))->toBeFalse();
    });
});
