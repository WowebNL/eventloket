<?php

use App\Enums\Role;
use App\Models\User;

test('Custom 403 page is shown when logged in user tries to access other panel', function () {
    $user = User::factory()->create([
        'role' => Role::Admin,
    ]);
    $this->actingAs($user);

    $response = $this->get(route('filament.advisor.tenant'));

    // Assert
    $response->assertStatus(403);
    $response->assertSee(__('errors/403.message'));
});
