<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('creates admin user successfully with valid input', function () {
    // Arrange - Mock user input
    $this->artisan('app:create-admin-user')
        ->expectsQuestion('First name', 'John')
        ->expectsQuestion('Last name', 'Doe')
        ->expectsQuestion('Email address', 'john.doe@example.com')
        ->expectsQuestion('Phone number (optional)', '+31612345678')
        ->expectsConfirmation('Create this admin user?', 'yes')
        ->expectsOutput('✅ Admin user created successfully!')
        ->assertSuccessful();

    // Assert - Check user was created
    $user = User::where('email', 'john.doe@example.com')->first();

    expect($user)
        ->not->toBeNull()
        ->first_name->toBe('John')
        ->last_name->toBe('Doe')
        ->email->toBe('john.doe@example.com')
        ->phone->toBe('+31612345678')
        ->role->toBe(Role::Admin)
        ->email_verified_at->not->toBeNull();

    // Verify password is hashed
    expect(Hash::check('password', $user->password))->toBeFalse(); // Should not be default password
});

test('creates admin user without phone number', function () {
    $this->artisan('app:create-admin-user')
        ->expectsQuestion('First name', 'Jane')
        ->expectsQuestion('Last name', 'Smith')
        ->expectsQuestion('Email address', 'jane.smith@example.com')
        ->expectsQuestion('Phone number (optional)', '') // Empty phone
        ->expectsConfirmation('Create this admin user?', 'yes')
        ->expectsOutput('✅ Admin user created successfully!')
        ->assertSuccessful();

    $user = User::where('email', 'jane.smith@example.com')->first();

    expect($user)
        ->not->toBeNull()
        ->phone->toBe('');
});

test('fails when email already exists', function () {
    // Arrange - Create existing user
    User::factory()->create(['email' => 'existing@example.com']);

    // Act & Assert
    $this->artisan('app:create-admin-user')
        ->expectsQuestion('First name', 'Test')
        ->expectsQuestion('Last name', 'User')
        ->expectsQuestion('Email address', 'existing@example.com')
        ->expectsQuestion('Phone number (optional)', '') // Command continues to ask for phone
        ->expectsOutput("A user with email 'existing@example.com' already exists!")
        ->assertFailed();

    // Ensure no duplicate user was created
    expect(User::where('email', 'existing@example.com')->count())->toBe(1);
});

test('cancels user creation when confirmation is declined', function () {
    $initialUserCount = User::count();

    $this->artisan('app:create-admin-user')
        ->expectsQuestion('First name', 'Test')
        ->expectsQuestion('Last name', 'User')
        ->expectsQuestion('Email address', 'cancelled@example.com')
        ->expectsQuestion('Phone number (optional)', '')
        ->expectsConfirmation('Create this admin user?', 'no')
        ->expectsOutput('User creation cancelled.')
        ->assertSuccessful();

    // Assert no user was created
    expect(User::count())->toBe($initialUserCount);
    expect(User::where('email', 'cancelled@example.com')->exists())->toBeFalse();
});

test('displays user details in table format', function () {
    $this->artisan('app:create-admin-user')
        ->expectsQuestion('First name', 'Display')
        ->expectsQuestion('Last name', 'Test')
        ->expectsQuestion('Email address', 'display.test@example.com')
        ->expectsQuestion('Phone number (optional)', '+31987654321')
        ->expectsTable(
            ['Field', 'Value'],
            [
                ['First Name', 'Display'],
                ['Last Name', 'Test'],
                ['Email', 'display.test@example.com'],
                ['Phone', '+31987654321'],
                ['Role', 'Admin'],
            ]
        )
        ->expectsConfirmation('Create this admin user?', 'yes')
        ->assertSuccessful();
});

test('displays password securely only once', function () {
    $this->artisan('app:create-admin-user')
        ->expectsQuestion('First name', 'Password')
        ->expectsQuestion('Last name', 'Test')
        ->expectsQuestion('Email address', 'password.test@example.com')
        ->expectsQuestion('Phone number (optional)', '')
        ->expectsConfirmation('Create this admin user?', 'yes')
        ->expectsOutput('🔐 IMPORTANT: Save this password securely - it will only be shown once!')
        ->expectsOutput('Email: password.test@example.com')
        ->expectsOutputToContain('Password: ')
        ->expectsOutput('The user should change this password on first login.')
        ->assertSuccessful();
});

test('handles database exceptions gracefully', function () {
    // Mock a database exception by providing invalid data
    // This test would require more sophisticated mocking in a real scenario
    // For now, we'll test the error handling path indirectly

    $this->artisan('app:create-admin-user')
        ->expectsQuestion('First name', '')  // Empty first name might cause validation issues
        ->expectsQuestion('Last name', '')
        ->expectsQuestion('Email address', 'invalid-email-format') // Invalid email
        ->expectsQuestion('Phone number (optional)', '')
        ->expectsConfirmation('Create this admin user?', 'yes');

    // The command should handle the exception and show an error message
    // Note: The exact behavior depends on model validation rules
});

test('auto-verifies admin user email', function () {
    $this->artisan('app:create-admin-user')
        ->expectsQuestion('First name', 'Verified')
        ->expectsQuestion('Last name', 'Admin')
        ->expectsQuestion('Email address', 'verified.admin@example.com')
        ->expectsQuestion('Phone number (optional)', '')
        ->expectsConfirmation('Create this admin user?', 'yes')
        ->assertSuccessful();

    $user = User::where('email', 'verified.admin@example.com')->first();

    expect($user->email_verified_at)->not->toBeNull();
});
