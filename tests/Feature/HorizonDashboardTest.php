<?php

use App\Enums\Role;
use App\Models\User;

beforeEach(function () {
    $this->dashboardUrl = '/'.config('horizon.path');
});

test('unauthenticated user cannot access horizon dashboard', function () {
    $this->get($this->dashboardUrl)->assertForbidden();
});

test('admin can access horizon dashboard', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->get($this->dashboardUrl)
        ->assertOk();
});

test('reviewer cannot access horizon dashboard', function () {
    $reviewer = User::factory()->create(['role' => Role::Reviewer]);

    $this->actingAs($reviewer)
        ->get($this->dashboardUrl)
        ->assertForbidden();
});

test('municipality admin cannot access horizon dashboard', function () {
    $municipalityAdmin = User::factory()->create(['role' => Role::MunicipalityAdmin]);

    $this->actingAs($municipalityAdmin)
        ->get($this->dashboardUrl)
        ->assertForbidden();
});

test('organiser cannot access horizon dashboard', function () {
    $organiser = User::factory()->create(['role' => Role::Organiser]);

    $this->actingAs($organiser)
        ->get($this->dashboardUrl)
        ->assertForbidden();
});

test('advisor cannot access horizon dashboard', function () {
    $advisor = User::factory()->create(['role' => Role::Advisor]);

    $this->actingAs($advisor)
        ->get($this->dashboardUrl)
        ->assertForbidden();
});
