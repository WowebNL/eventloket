<?php

use App\Enums\Role;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;

beforeEach(function () {
    $this->zaaktype = Zaaktype::factory()->create();
});

test('admin user can delete imported zaak via policy', function () {
    $adminUser = User::factory()->create(['role' => Role::Admin]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => null,
        'imported_data' => ['some' => 'data'],
    ]);

    expect($adminUser->can('deleteImported', $zaak))->toBeTrue();
});

test('organiser user cannot delete imported zaak via policy', function () {
    $organiserUser = User::factory()->create(['role' => Role::Organiser]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => null,
        'imported_data' => ['some' => 'data'],
    ]);

    expect($organiserUser->can('deleteImported', $zaak))->toBeFalse();
});

test('reviewer user cannot delete imported zaak via policy', function () {
    $reviewerUser = User::factory()->create(['role' => Role::Reviewer]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => null,
        'imported_data' => ['some' => 'data'],
    ]);

    expect($reviewerUser->can('deleteImported', $zaak))->toBeFalse();
});

test('municipality admin user cannot delete imported zaak via policy', function () {
    $municipalityAdminUser = User::factory()->create(['role' => Role::MunicipalityAdmin]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => null,
        'imported_data' => ['some' => 'data'],
    ]);

    expect($municipalityAdminUser->can('deleteImported', $zaak))->toBeFalse();
});

test('admin user cannot delete non-imported zaak via policy', function () {
    $adminUser = User::factory()->create(['role' => Role::Admin]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => 'https://example.com/zaak/1',
        'imported_data' => null,
    ]);

    expect($adminUser->can('deleteImported', $zaak))->toBeFalse();
});

test('policy check works correctly for all roles', function () {
    $roles = [
        'admin' => Role::Admin,
        'organiser' => Role::Organiser,
        'reviewer' => Role::Reviewer,
        'municipality_admin' => Role::MunicipalityAdmin,
        'reviewer_municipality_admin' => Role::ReviewerMunicipalityAdmin,
    ];

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => null,
        'imported_data' => ['some' => 'data'],
    ]);

    foreach ($roles as $roleName => $role) {
        $user = User::factory()->create(['role' => $role]);

        if ($roleName === 'admin') {
            expect($user->can('deleteImported', $zaak))->toBeTrue();
        } else {
            expect($user->can('deleteImported', $zaak))->toBeFalse();
        }
    }
});
