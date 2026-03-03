<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('allows users to authenticate with any email case combination', function () {
    $user = User::factory()->create([
        'email' => 'Test@Example.COM',
        'password' => Hash::make('password'),
    ]);

    // Email should be stored as lowercase
    expect($user->fresh()->email)->toBe('test@example.com');

    // Test authentication with various case combinations
    expect(auth()->attempt(['email' => 'test@example.com', 'password' => 'password']))->toBeTrue();
    auth()->logout();

    expect(auth()->attempt(['email' => 'Test@Example.com', 'password' => 'password']))->toBeTrue();
    auth()->logout();

    expect(auth()->attempt(['email' => 'TEST@EXAMPLE.COM', 'password' => 'password']))->toBeTrue();
    auth()->logout();

    expect(auth()->attempt(['email' => 'TeSt@ExAmPlE.cOm', 'password' => 'password']))->toBeTrue();
});

it('stores emails in lowercase', function () {
    $user = User::factory()->create([
        'email' => 'UPPERCASE@EXAMPLE.COM',
    ]);

    expect($user->email)->toBe('uppercase@example.com');
    expect($user->fresh()->email)->toBe('uppercase@example.com');
});

it('stores updated emails in lowercase', function () {
    $user = User::factory()->create([
        'email' => 'original@example.com',
    ]);

    $user->update(['email' => 'UPDATED@EXAMPLE.COM']);

    expect($user->email)->toBe('updated@example.com');
    expect($user->fresh()->email)->toBe('updated@example.com');
});
