<?php

use App\Enums\Role;
use App\Models\Municipality;
use App\Models\User;
use App\Models\Users\AdminUser;
use App\Models\Users\AdvisorUser;
use App\Models\Users\MunicipalityAdminUser;
use Illuminate\Support\Facades\Event;

test('user model uses soft deletes', function () {
    $adminUser = User::factory()->create(['role' => Role::Admin]);

    expect($adminUser->delete())->toBeTrue()
        ->and($adminUser->trashed())->toBeTrue()
        ->and($adminUser->deleted_at)->not->toBeNull();
});

test('soft deleted users are excluded from default queries', function () {
    $adminUser = User::factory()->create(['role' => Role::Admin]);
    $userId = $adminUser->id;

    $initialCount = User::count();
    $adminUser->delete();

    expect(User::find($userId))->toBeNull()
        ->and(User::count())->toBe($initialCount - 1)
        ->and(User::withTrashed()->count())->toBe($initialCount)
        ->and(User::onlyTrashed()->count())->toBe(1);
});

test('soft deleted users can be restored', function () {
    $adminUser = User::factory()->create(['role' => Role::Admin]);
    $initialCount = User::count();

    $adminUser->delete();

    expect($adminUser->trashed())->toBeTrue();

    $adminUser->restore();

    expect($adminUser->trashed())->toBeFalse()
        ->and($adminUser->deleted_at)->toBeNull()
        ->and(User::count())->toBe($initialCount);
});

test('users can be force deleted permanently', function () {
    $adminUser = User::factory()->create(['role' => Role::Admin]);
    $userId = $adminUser->id;
    $initialCount = User::withTrashed()->count();

    $adminUser->forceDelete();

    expect(User::withTrashed()->find($userId))->toBeNull()
        ->and(User::withTrashed()->count())->toBe($initialCount - 1);
});

test('user subclasses maintain soft delete functionality', function () {
    // Use the base User factory with specific roles instead of subclass factories
    $adminUser = User::factory()->create(['role' => Role::Admin]);
    $municipalityUser = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $advisorUser = User::factory()->create(['role' => Role::Advisor]);

    $adminUser->delete();
    $municipalityUser->delete();
    $advisorUser->delete();

    expect($adminUser->trashed())->toBeTrue()
        ->and($municipalityUser->trashed())->toBeTrue()
        ->and($advisorUser->trashed())->toBeTrue()
        ->and(AdminUser::onlyTrashed()->count())->toBe(1)
        ->and(MunicipalityAdminUser::onlyTrashed()->count())->toBe(1)
        ->and(AdvisorUser::onlyTrashed()->count())->toBe(1);
});

test('relationships are maintained after soft delete', function () {
    $municipalityAdminUser = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $municipality = Municipality::factory()->create();
    $municipalityAdminUser->municipalities()->attach($municipality);

    $municipalityAdminUser->delete();

    // Relationships should still exist when accessing with trashed
    $trashedUser = User::withTrashed()->find($municipalityAdminUser->id);

    expect($trashedUser->municipalities()->count())->toBe(1)
        ->and($trashedUser->municipalities->first()->id)->toBe($municipality->id);
});

test('factory creates correct user subclass after soft delete operations', function () {
    $adminUser = User::factory()->create(['role' => Role::Admin]);

    // Test that the factory pattern still works correctly with soft deletes
    $adminUser->delete();
    $adminUser->restore();

    expect($adminUser)->toBeInstanceOf(AdminUser::class)
        ->and($adminUser->role)->toBe(Role::Admin);

    // Test creating new user after soft delete operations
    $newAdmin = User::factory()->create(['role' => Role::Admin]);
    expect($newAdmin)->toBeInstanceOf(AdminUser::class);
});

test('soft delete does not affect user authentication', function () {
    $adminUser = User::factory()->create(['role' => Role::Admin]);

    // Before deletion, user should be able to authenticate
    expect($adminUser->deleted_at)->toBeNull();

    $adminUser->delete();

    // After soft deletion, user should be marked as deleted but authentication methods should still work on the instance
    expect($adminUser->trashed())->toBeTrue()
        ->and($adminUser->getAuthIdentifier())->toBe($adminUser->id)
        ->and($adminUser->getAuthPassword())->toBe($adminUser->password);
});

test('mass delete operations work with soft deletes', function () {
    User::factory()->create(['role' => Role::Admin]);
    User::factory()->create(['role' => Role::Admin]);

    $initialCount = User::count();
    $adminCount = User::where('role', Role::Admin)->count();

    User::where('role', Role::Admin)->delete();

    expect(User::where('role', Role::Admin)->count())->toBe(0)
        ->and(User::onlyTrashed()->where('role', Role::Admin)->count())->toBe($adminCount)
        ->and(User::count())->toBe($initialCount - $adminCount);
});

test('soft delete respects eloquent events', function () {
    Event::fake([
        'eloquent.deleting: App\Models\Users\AdminUser',
        'eloquent.deleted: App\Models\Users\AdminUser',
    ]);

    $adminUser = User::factory()->create(['role' => Role::Admin]);

    $adminUser->delete();

    Event::assertDispatched('eloquent.deleting: App\Models\Users\AdminUser');
    Event::assertDispatched('eloquent.deleted: App\Models\Users\AdminUser');
});

test('soft delete timestamp is recorded correctly', function () {
    $adminUser = User::factory()->create(['role' => Role::Admin]);
    $beforeDelete = now()->startOfSecond(); // Remove microseconds to match database precision

    $adminUser->delete();

    expect($adminUser->deleted_at)->not->toBeNull()
        ->and($adminUser->deleted_at->greaterThanOrEqualTo($beforeDelete))->toBeTrue();
});
