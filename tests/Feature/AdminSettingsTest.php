<?php

use App\Enums\Role;
use App\Filament\Clusters\AdminSettings;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

test('admin settings cluster is accessible only to admin users', function () {
    $adminUser = User::factory()->create(['role' => Role::Admin]);
    $regularUser = User::factory()->create(['role' => Role::Reviewer]);

    Auth::login($adminUser);
    expect(AdminSettings::canAccess())->toBeTrue();

    Auth::login($regularUser);
    expect(AdminSettings::canAccess())->toBeFalse();
});
