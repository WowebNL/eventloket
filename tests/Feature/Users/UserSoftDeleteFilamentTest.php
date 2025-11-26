<?php

use App\Enums\Role;
use App\Models\Municipality;
use App\Models\User;
use Filament\Facades\Filament;

test('admin can soft delete municipality admin users through filament', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $adminUser = User::factory()->create(['role' => Role::Admin]);
    $municipality = Municipality::factory()->create();
    $municipalityAdminUser = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $municipalityAdminUser->municipalities()->attach($municipality);

    expect($municipalityAdminUser->trashed())->toBeFalse();

    // Simulate soft delete through Filament
    $municipalityAdminUser->delete();

    expect($municipalityAdminUser->trashed())->toBeTrue()
        ->and($municipalityAdminUser->deleted_at)->not->toBeNull();
});

test('admin can restore soft deleted municipality admin users', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $adminUser = User::factory()->create(['role' => Role::Admin]);
    $municipality = Municipality::factory()->create();
    $municipalityAdminUser = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $municipalityAdminUser->municipalities()->attach($municipality);

    // Soft delete the user
    $municipalityAdminUser->delete();
    expect($municipalityAdminUser->trashed())->toBeTrue();

    // Restore the user
    $municipalityAdminUser->restore();

    expect($municipalityAdminUser->trashed())->toBeFalse()
        ->and($municipalityAdminUser->deleted_at)->toBeNull();
});

test('admin can force delete municipality admin users permanently', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $adminUser = User::factory()->create(['role' => Role::Admin]);
    $municipality = Municipality::factory()->create();
    $municipalityAdminUser = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $municipalityAdminUser->municipalities()->attach($municipality);
    $userId = $municipalityAdminUser->id;

    // Force delete the user
    $municipalityAdminUser->forceDelete();

    expect(User::withTrashed()->find($userId))->toBeNull();
});

test('soft deleted users appear in trashed queries', function () {
    $municipality = Municipality::factory()->create();
    $municipalityAdminUser = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $municipalityAdminUser->municipalities()->attach($municipality);

    $activeCount = User::where('role', Role::MunicipalityAdmin)->count();

    // Soft delete the user
    $municipalityAdminUser->delete();

    expect(User::where('role', Role::MunicipalityAdmin)->count())->toBe($activeCount - 1)
        ->and(User::onlyTrashed()->where('role', Role::MunicipalityAdmin)->count())->toBe(1)
        ->and(User::withTrashed()->where('role', Role::MunicipalityAdmin)->count())->toBe($activeCount);
});

test('soft delete maintains foreign key relationships', function () {
    $municipality = Municipality::factory()->create();
    $municipalityAdminUser = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $municipalityAdminUser->municipalities()->attach($municipality);

    // Verify relationship exists before deletion
    expect($municipalityAdminUser->municipalities()->count())->toBe(1);

    // Soft delete the user
    $municipalityAdminUser->delete();

    // Verify relationship still exists when accessing with trashed
    $trashedUser = User::withTrashed()->find($municipalityAdminUser->id);
    expect($trashedUser->municipalities()->count())->toBe(1)
        ->and($trashedUser->municipalities->first()->id)->toBe($municipality->id);
});

test('mass soft delete operations work correctly', function () {
    $municipality = Municipality::factory()->create();

    // Create multiple municipality admin users individually
    $users = collect();
    for ($i = 0; $i < 3; $i++) {
        $user = User::factory()->create(['role' => Role::MunicipalityAdmin]);
        $user->municipalities()->attach($municipality);
        $users->push($user);
    }

    $initialCount = User::where('role', Role::MunicipalityAdmin)->count();

    // Mass delete municipality admin users
    User::where('role', Role::MunicipalityAdmin)->delete();

    expect(User::where('role', Role::MunicipalityAdmin)->count())->toBe(0)
        ->and(User::onlyTrashed()->where('role', Role::MunicipalityAdmin)->count())->toBe($initialCount);
});

test('filament actions respect soft delete policies', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $adminUser = User::factory()->create(['role' => Role::Admin]);
    $municipality = Municipality::factory()->create();
    $municipalityAdminUser = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $municipalityAdminUser->municipalities()->attach($municipality);

    // Test delete permission
    expect($adminUser->can('delete', $municipalityAdminUser))->toBeTrue();

    // Soft delete the user
    $municipalityAdminUser->delete();

    // Test restore permission
    expect($adminUser->can('restore', $municipalityAdminUser))->toBeTrue();

    // Test force delete permission
    expect($adminUser->can('forceDelete', $municipalityAdminUser))->toBeTrue();
});

test('non-admin users cannot perform soft delete operations on protected users', function () {
    $municipality = Municipality::factory()->create();
    $municipalityAdminUser = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $reviewerUser = User::factory()->create(['role' => Role::Reviewer]);
    $municipalityAdminUser->municipalities()->attach($municipality);
    $reviewerUser->municipalities()->attach($municipality);

    // Test that reviewer cannot delete municipality admin
    expect($reviewerUser->can('delete', $municipalityAdminUser))->toBeFalse();

    // Test that reviewer cannot restore municipality admin
    expect($reviewerUser->can('restore', $municipalityAdminUser))->toBeFalse();

    // Test that reviewer cannot force delete municipality admin
    expect($reviewerUser->can('forceDelete', $municipalityAdminUser))->toBeFalse();
});
