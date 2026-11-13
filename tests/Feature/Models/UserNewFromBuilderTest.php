<?php

declare(strict_types=1);

/**
 * Users are hydrated into a role-specific subclass. Eloquent also hydrates
 * models from partial rows, for example when plucking an attribute that has
 * an accessor, and such a row does not carry the role.
 */

use App\Enums\Role;
use App\Models\User;
use App\Models\Users\AdvisorUser;

test('plucking an attribute with an accessor works without the role column', function () {
    $user = User::factory()->create(['role' => Role::Advisor]);

    expect(User::query()->pluck('name', 'id')->all())
        ->toBe([$user->id => $user->name]);
});

test('a user loaded with its role is still hydrated into the role class', function () {
    $user = User::factory()->create(['role' => Role::Advisor]);

    expect(User::query()->find($user->id))->toBeInstanceOf(AdvisorUser::class);
});
