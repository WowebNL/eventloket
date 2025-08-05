<?php

use App\Enums\Role;
use App\Filament\Clusters\AdminSettings\Resources\AdminResource\Pages\ListAdmins;
use App\Filament\Pages\AcceptAdminInvite;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Mail\AdminInviteMail;
use App\Models\AdminInvite;
use App\Models\Municipality;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Mail::fake();

    $this->municipality = Municipality::factory()->create([
        'name' => 'Test Municipality',
    ]);

    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => Role::Admin,
    ]);

    $this->municipality->users()->attach($this->admin);
});

// Tests for Reviewer role
test('admin can create a reviewer invite', function () {
    // Arrange
    $this->actingAs($this->admin);
    $inviteeEmail = 'reviewer@example.com';

    Filament::setTenant($this->municipality);

    // Act
    $response = livewire(ListUsers::class)
        ->callAction('invite', [
            'email' => $inviteeEmail,
        ]);

    // Assert
    $response->assertSuccessful();

    $invite = AdminInvite::where('email', $inviteeEmail)->first();
    expect($invite)->not->toBeNull()
        ->and($invite->municipalities()->first()->id)->toBe($this->municipality->id)
        ->and($invite->role)->toBe(Role::Reviewer);

    Mail::assertSent(AdminInviteMail::class, function ($mail) use ($inviteeEmail) {
        return $mail->hasTo($inviteeEmail);
    });
});

test('existing user can accept a reviewer invite', function () {
    // Arrange
    $user = User::factory()->create([
        'email' => 'existinguser@example.com',
    ]);

    $invite = AdminInvite::create([
        'email' => $user->email,
        'role' => Role::Reviewer,
        'token' => Str::uuid(),
    ]);

    $invite->municipalities()->attach($this->municipality->id);

    // Act
    $this->actingAs($user);
    $signedUrl = URL::signedRoute('admin-invites.accept', [
        'token' => $invite->token,
    ]);

    // Assert
    $this->get($signedUrl)
        ->assertOk()
        ->assertSeeLivewire(AcceptAdminInvite::class);

    // Test the accept invite action
    $response = livewire(AcceptAdminInvite::class, ['token' => $invite->token])
        ->call('acceptInvite');

    $response->assertRedirect(route('filament.admin.pages.dashboard', ['tenant' => $this->municipality->id]));

    $this->assertDatabaseHas('municipality_user', [
        'municipality_id' => $this->municipality->id,
        'user_id' => $user->id,
    ]);

    $this->assertDatabaseMissing('admin_invites', [
        'id' => $invite->id,
    ]);
});

test('new user can register and accept a reviewer invite', function () {
    // Arrange
    $inviteeEmail = 'newreviewer@example.com';
    $invite = AdminInvite::create([
        'email' => $inviteeEmail,
        'role' => Role::Reviewer,
        'token' => Str::uuid(),
    ]);

    $invite->municipalities()->attach($this->municipality->id);

    $signedUrl = URL::signedRoute('admin-invites.accept', [
        'token' => $invite->token,
    ]);

    // Assert
    $this->get($signedUrl)
        ->assertOk()
        ->assertSeeLivewire(AcceptAdminInvite::class);

    // Test the registration and accept invite action
    $response = livewire(AcceptAdminInvite::class, ['token' => $invite->token])
        ->fillForm([
            'name' => 'New Reviewer',
            'phone' => '1234567890',
            'password' => 'password',
            'passwordConfirmation' => 'password',
        ])
        ->call('create');

    // Assert
    $response->assertRedirect(route('filament.admin.pages.dashboard', ['tenant' => $this->municipality->id]));

    $user = User::where('email', $inviteeEmail)->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('New Reviewer')
        ->and($user->phone)->toBe('1234567890')
        ->and($user->role)->toBe(Role::Reviewer);

    $this->assertDatabaseHas('municipality_user', [
        'municipality_id' => $this->municipality->id,
        'user_id' => $user->id,
    ]);

    $this->assertDatabaseMissing('admin_invites', [
        'id' => $invite->id,
    ]);
});

// Tests for MunicipalityAdmin role
test('admin can create a municipality admin invite', function () {
    // Arrange
    $this->actingAs($this->admin);
    $inviteeEmail = 'municipalityadmin@example.com';

    Filament::setTenant($this->municipality);

    // Act
    $response = livewire(ListAdmins::class)
        ->callAction('invite', [
            'email' => $inviteeEmail,
            'role' => Role::MunicipalityAdmin,
            'municipalities' => [$this->municipality->id],
        ]);

    // Assert
    $response->assertSuccessful();

    $invite = AdminInvite::where('email', $inviteeEmail)->first();
    expect($invite)->not->toBeNull()
        ->and($invite->municipalities()->first()->id)->toBe($this->municipality->id)
        ->and($invite->role)->toBe(Role::MunicipalityAdmin);

    Mail::assertSent(AdminInviteMail::class, function ($mail) use ($inviteeEmail) {
        return $mail->hasTo($inviteeEmail);
    });
});

test('existing user can accept a municipality admin invite', function () {
    // Arrange
    $user = User::factory()->create([
        'email' => 'existingmunicadmin@example.com',
    ]);

    $invite = AdminInvite::create([
        'email' => $user->email,
        'role' => Role::MunicipalityAdmin,
        'token' => Str::uuid(),
    ]);

    $invite->municipalities()->attach($this->municipality->id);

    // Act
    $this->actingAs($user);
    $signedUrl = URL::signedRoute('admin-invites.accept', [
        'token' => $invite->token,
    ]);

    // Assert
    $this->get($signedUrl)
        ->assertOk()
        ->assertSeeLivewire(AcceptAdminInvite::class);

    // Test the accept invite action
    $response = livewire(AcceptAdminInvite::class, ['token' => $invite->token])
        ->call('acceptInvite');

    $response->assertRedirect(route('filament.admin.pages.dashboard', ['tenant' => $this->municipality->id]));

    $this->assertDatabaseHas('municipality_user', [
        'municipality_id' => $this->municipality->id,
        'user_id' => $user->id,
    ]);

    $this->assertDatabaseMissing('admin_invites', [
        'id' => $invite->id,
    ]);
});

test('new user can register and accept a municipality admin invite', function () {
    // Arrange
    $inviteeEmail = 'newmunicadmin@example.com';
    $invite = AdminInvite::create([
        'email' => $inviteeEmail,
        'role' => Role::MunicipalityAdmin,
        'token' => Str::uuid(),
    ]);

    $invite->municipalities()->attach($this->municipality->id);

    $signedUrl = URL::signedRoute('admin-invites.accept', [
        'token' => $invite->token,
    ]);

    // Assert
    $this->get($signedUrl)
        ->assertOk()
        ->assertSeeLivewire(AcceptAdminInvite::class);

    // Test the registration and accept invite action
    $response = livewire(AcceptAdminInvite::class, ['token' => $invite->token])
        ->fillForm([
            'name' => 'New Municipality Admin',
            'phone' => '1234567890',
            'password' => 'password',
            'passwordConfirmation' => 'password',
        ])
        ->call('create');

    // Assert
    $response->assertRedirect(route('filament.admin.pages.dashboard', ['tenant' => $this->municipality->id]));

    $user = User::where('email', $inviteeEmail)->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('New Municipality Admin')
        ->and($user->phone)->toBe('1234567890')
        ->and($user->role)->toBe(Role::MunicipalityAdmin);

    $this->assertDatabaseHas('municipality_user', [
        'municipality_id' => $this->municipality->id,
        'user_id' => $user->id,
    ]);

    $this->assertDatabaseMissing('admin_invites', [
        'id' => $invite->id,
    ]);
});

// Tests for Admin role
test('admin can create an admin invite', function () {
    // Arrange
    $this->actingAs($this->admin);
    $inviteeEmail = 'newadmin@example.com';

    Filament::setTenant($this->municipality);

    // Act
    $response = livewire(ListAdmins::class)
        ->callAction('invite', [
            'email' => $inviteeEmail,
            'role' => Role::Admin,
        ]);

    // Assert
    $response->assertSuccessful();
    $invite = AdminInvite::where('email', $inviteeEmail)->first();

    expect($invite)->not->toBeNull()
        ->and($invite->role)->toBe(Role::Admin);

    Mail::assertSent(AdminInviteMail::class, function ($mail) use ($inviteeEmail) {
        return $mail->hasTo($inviteeEmail);
    });
});

test('existing user can accept an admin invite', function () {
    // Arrange
    $user = User::factory()->create([
        'email' => 'existingadmin@example.com',
    ]);

    $invite = AdminInvite::create([
        'email' => $user->email,
        'role' => Role::Admin,
        'token' => Str::uuid(),
    ]);

    $invite->municipalities()->attach($this->municipality->id);

    // Act
    $this->actingAs($user);
    $signedUrl = URL::signedRoute('admin-invites.accept', [
        'token' => $invite->token,
    ]);

    // Assert
    $this->get($signedUrl)
        ->assertOk()
        ->assertSeeLivewire(AcceptAdminInvite::class);

    // Test the accept invite action
    $response = livewire(AcceptAdminInvite::class, ['token' => $invite->token])
        ->call('acceptInvite');

    $response->assertRedirect(route('filament.admin.pages.dashboard', ['tenant' => $this->municipality->id]));

    // For Admin role, we don't attach to municipality
    $this->assertDatabaseMissing('municipality_user', [
        'municipality_id' => $this->municipality->id,
        'user_id' => $user->id,
    ]);

    $this->assertDatabaseMissing('admin_invites', [
        'id' => $invite->id,
    ]);
});

test('new user can register and accept an admin invite', function () {
    // Arrange
    $inviteeEmail = 'brandnewadmin@example.com';
    $invite = AdminInvite::create([
        'email' => $inviteeEmail,
        'role' => Role::Admin,
        'token' => Str::uuid(),
    ]);

    $signedUrl = URL::signedRoute('admin-invites.accept', [
        'token' => $invite->token,
    ]);

    // Assert
    $this->get($signedUrl)
        ->assertOk()
        ->assertSeeLivewire(AcceptAdminInvite::class);

    // Test the registration and accept invite action
    $response = livewire(AcceptAdminInvite::class, ['token' => $invite->token])
        ->fillForm([
            'name' => 'New Global Admin',
            'phone' => '1234567890',
            'password' => 'password',
            'passwordConfirmation' => 'password',
        ])
        ->call('create');

    // Assert
    $response->assertRedirect(route('filament.admin.pages.dashboard', ['tenant' => $this->municipality->id]));

    $user = User::where('email', $inviteeEmail)->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('New Global Admin')
        ->and($user->phone)->toBe('1234567890')
        ->and($user->role)->toBe(Role::Admin);

    // For Admin role, we don't attach to municipality
    $this->assertDatabaseMissing('municipality_user', [
        'municipality_id' => $this->municipality->id,
        'user_id' => $user->id,
    ]);

    $this->assertDatabaseMissing('admin_invites', [
        'id' => $invite->id,
    ]);
});

test('invite cannot be accepted by wrong user', function () {
    // Arrange
    $user = User::factory()->create([
        'email' => 'existinguser@example.com',
    ]);

    $invite = AdminInvite::create([
        'email' => 'differentuser@example.com', // Different email than logged-in user
        'token' => Str::uuid(),
        'role' => Role::Reviewer,
    ]);

    $invite->municipalities()->attach($this->municipality->id);

    // Act
    $this->actingAs($user);
    $response = livewire(AcceptAdminInvite::class, ['token' => $invite->token])
        ->call('acceptInvite');

    // Assert
    $response->assertStatus(403);

    $this->assertDatabaseMissing('municipality_user', [
        'municipality_id' => $this->municipality->id,
        'user_id' => $user->id,
    ]);

    $this->assertDatabaseHas('admin_invites', [
        'id' => $invite->id,
    ]);
});

test('municipality admin cannot invite admin users', function () {
    // Arrange
    $municipalityAdmin = User::factory()->create([
        'email' => 'municadmin@example.com',
        'role' => Role::MunicipalityAdmin,
    ]);
    $this->municipality->users()->attach($municipalityAdmin);

    $this->actingAs($municipalityAdmin);
    $inviteeEmail = 'newadmin@example.com';

    Filament::setTenant($this->municipality);

    // Act & Assert
    $response = livewire(ListAdmins::class)
        ->callAction('invite', [
            'email' => $inviteeEmail,
            'role' => Role::Admin,
        ]);

    $this->assertDatabaseMissing('admin_invites', [
        'email' => $inviteeEmail,
        'role' => Role::Admin,
    ]);
});
