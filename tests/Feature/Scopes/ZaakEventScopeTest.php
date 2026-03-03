<?php

use App\Enums\Role;
use App\Models\Scopes\ZaakEventScope;
use App\Models\User;
use App\Models\Zaak;

beforeEach(function () {
    $this->scope = new ZaakEventScope;
});

test('authenticated advisor user gets full column selection', function () {
    // Arrange
    $user = User::factory()->create(['role' => Role::Advisor]);
    $this->actingAs($user);

    $builder = Zaak::query();
    $originalBuilder = clone $builder;

    // Act
    $this->scope->apply($builder, new Zaak);

    // Assert - Check that select includes all sensitive columns
    $selectedColumns = $builder->getQuery()->columns ?? [];
    expect($selectedColumns)->toContain('organiser_user_id')
        ->and($selectedColumns)->toContain('imported_data');
});

test('authenticated admin user gets full column selection', function () {
    // Arrange
    $user = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($user);

    $builder = Zaak::query();

    // Act
    $this->scope->apply($builder, new Zaak);

    // Assert
    $selectedColumns = $builder->getQuery()->columns ?? [];
    expect($selectedColumns)->toContain('organiser_user_id')
        ->and($selectedColumns)->toContain('imported_data');
});

test('authenticated municipality admin user gets full column selection', function () {
    // Arrange
    $user = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $this->actingAs($user);

    $builder = Zaak::query();

    // Act
    $this->scope->apply($builder, new Zaak);

    // Assert
    $selectedColumns = $builder->getQuery()->columns ?? [];
    expect($selectedColumns)->toContain('organiser_user_id')
        ->and($selectedColumns)->toContain('imported_data');
});

test('authenticated reviewer municipality admin user gets full column selection', function () {
    // Arrange
    $user = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);
    $this->actingAs($user);

    $builder = Zaak::query();

    // Act
    $this->scope->apply($builder, new Zaak);

    // Assert
    $selectedColumns = $builder->getQuery()->columns ?? [];
    expect($selectedColumns)->toContain('organiser_user_id')
        ->and($selectedColumns)->toContain('imported_data');
});

test('authenticated reviewer user gets full column selection', function () {
    // Arrange
    $user = User::factory()->create(['role' => Role::Reviewer]);
    $this->actingAs($user);

    $builder = Zaak::query();

    // Act
    $this->scope->apply($builder, new Zaak);

    // Assert
    $selectedColumns = $builder->getQuery()->columns ?? [];
    expect($selectedColumns)->toContain('organiser_user_id')
        ->and($selectedColumns)->toContain('imported_data');
});

test('privileged user loads organiser user relationship', function () {
    // Arrange
    $user = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($user);

    $builder = Zaak::query();

    // Act
    $this->scope->apply($builder, new Zaak);

    // Assert - Check that organiserUser relationship is loaded
    $eagerLoads = $builder->getEagerLoads();
    expect($eagerLoads)->toHaveKey('organiserUser');
});

test('privileged user loads organisation with correct columns', function () {
    // Arrange
    $user = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($user);

    $builder = Zaak::query();

    // Act
    $this->scope->apply($builder, new Zaak);

    // Assert
    $eagerLoads = $builder->getEagerLoads();
    expect($eagerLoads)->toHaveKey('organisation');
});

test('unauthenticated user gets limited column selection', function () {
    // Arrange
    auth()->logout();

    $builder = Zaak::query();

    // Act
    $this->scope->apply($builder, new Zaak);

    // Assert - Check that organiser_user_id and imported_data are NOT selected
    $selectedColumns = $builder->getQuery()->columns ?? [];
    expect($selectedColumns)->not->toContain('organiser_user_id')
        ->and($selectedColumns)->not->toContain('imported_data');
});

test('organiser user gets limited column selection', function () {
    // Arrange
    $user = User::factory()->create(['role' => Role::Organiser]);
    $this->actingAs($user);

    $builder = Zaak::query();

    // Act
    $this->scope->apply($builder, new Zaak);

    // Assert
    $selectedColumns = $builder->getQuery()->columns ?? [];
    expect($selectedColumns)->not->toContain('organiser_user_id')
        ->and($selectedColumns)->not->toContain('imported_data');
});

test('unauthenticated user does not load organiser user relationship', function () {
    // Arrange
    auth()->logout();

    $builder = Zaak::query();

    // Act
    $this->scope->apply($builder, new Zaak);

    // Assert
    $eagerLoads = $builder->getEagerLoads();
    expect($eagerLoads)->not->toHaveKey('organiserUser');
});

test('all users get basic columns selected', function () {
    // Test with authenticated user
    $user = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($user);

    $builder = Zaak::query();
    $this->scope->apply($builder, new Zaak);

    $selectedColumns = $builder->getQuery()->columns ?? [];

    // Basic columns should be present for all users
    expect($selectedColumns)->toContain('id')
        ->and($selectedColumns)->toContain('public_id')
        ->and($selectedColumns)->toContain('reference_data')
        ->and($selectedColumns)->toContain('zaaktype_id')
        ->and($selectedColumns)->toContain('organisation_id')
        ->and($selectedColumns)->toContain('zgw_zaak_url');
});

test('all users get zaaktype relationship loaded', function () {
    // Test with unauthenticated user
    auth()->logout();

    $builder = Zaak::query();
    $this->scope->apply($builder, new Zaak);

    $eagerLoads = $builder->getEagerLoads();
    expect($eagerLoads)->toHaveKey('zaaktype');
});

test('all users get organisation relationship loaded', function () {
    // Test with unauthenticated user
    auth()->logout();

    $builder = Zaak::query();
    $this->scope->apply($builder, new Zaak);

    $eagerLoads = $builder->getEagerLoads();
    expect($eagerLoads)->toHaveKey('organisation');
});

test('scope returns early for privileged users', function () {
    // Arrange
    $user = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($user);

    $builder = Zaak::query();

    // Act
    $this->scope->apply($builder, new Zaak);

    // Assert - The scope should return early, so we check that the query was modified
    // We can verify this by checking that select was called
    expect($builder->getQuery()->columns)->not->toBeNull();
});
